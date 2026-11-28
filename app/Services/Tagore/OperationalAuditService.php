<?php

namespace App\Services\Tagore;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class OperationalAuditService
{
    public function generateInstance(
        int $templateId,
        int $institutionId,
        int $assigneeUserId,
        string $roleCode,
        string $scopeType,
        ?int $scopeId,
        CarbonInterface $scheduledFor,
        CarbonInterface $dueAt,
        array $templateSnapshot
    ): int {
        $template = DB::table('tagore_operational_audit_templates')->where('id', $templateId)->where('status', 'active')->first();
        if (!$template || ($template->institution_id !== null && (int) $template->institution_id !== $institutionId)) {
            throw new InvalidArgumentException('An active audit template for this institution is required.');
        }

        if (!DB::table('tagore_institutions')->where('id', $institutionId)->where('status', 'active')->whereNull('deleted_at')->exists()) {
            throw new InvalidArgumentException('The target institution must be active.');
        }

        $scheduledDate = $scheduledFor->toDateString();
        if (($template->effective_from && $scheduledDate < $template->effective_from)
            || ($template->effective_until && $scheduledDate > $template->effective_until)) {
            throw new InvalidArgumentException('The audit template is not effective on the scheduled date.');
        }
        if ($dueAt->lessThan($scheduledFor)) {
            throw new InvalidArgumentException('The audit due time cannot be earlier than its scheduled time.');
        }

        $scopeType = strtoupper(trim($scopeType));
        if (!in_array($scopeType, ['INSTITUTION', 'DEPARTMENT', 'HOSTEL', 'ROOM', 'CLASS', 'SECTION', 'TRANSPORT', 'VEHICLE'], true)) {
            throw new InvalidArgumentException('Unsupported audit scope type.');
        }
        if ($scopeType === 'INSTITUTION' && (int) $scopeId !== $institutionId) {
            throw new InvalidArgumentException('Institution-scoped audits must reference their own institution.');
        }

        $templateControls = json_decode((string) $template->controls_json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($templateControls) || $templateControls === []) {
            throw new InvalidArgumentException('The active audit template must define at least one control.');
        }
        $controlKeys = [];
        foreach ($templateControls as $control) {
            $controlKey = trim((string) ($control['key'] ?? ''));
            $evidenceClass = strtoupper((string) ($control['evidence_class'] ?? 'C'));
            if ($controlKey === '' || isset($controlKeys[$controlKey]) || !in_array($evidenceClass, ['A', 'B', 'C'], true)) {
                throw new InvalidArgumentException('Audit template controls require unique keys and evidence classes A, B or C.');
            }
            $controlKeys[$controlKey] = $evidenceClass;
        }
        $templateSnapshot['template_code'] = $template->template_code;
        $templateSnapshot['template_version'] = (int) $template->version;
        $templateSnapshot['controls'] = $templateControls;
        $roleCode = strtoupper(trim($roleCode));
        if ($roleCode === '' || $roleCode !== strtoupper((string) $template->role_code)) {
            throw new InvalidArgumentException('The assigned role must match the audit template role.');
        }

        $assignment = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $assigneeUserId)
            ->where('r.code', $roleCode)
            ->where('ur.status', 'active')
            ->where(function ($query) use ($scheduledFor) {
                $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', $scheduledFor->toDateString());
            })
            ->where(function ($query) use ($scheduledFor) {
                $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', $scheduledFor->toDateString());
            });

        if ($roleCode === 'OWNER') {
            $assignment->where(fn ($builder) => $builder->whereNull('ur.institution_id')->orWhere('ur.institution_id', $institutionId));
        } else {
            $assignment->where('ur.institution_id', $institutionId);
        }

        if (!$assignment->exists()) {
            throw new InvalidArgumentException('The assignee must hold an active role assignment for the requested institution and date.');
        }

        $scheduledDate = $scheduledFor->toDateString();
        $key = hash('sha256', implode('|', [
            $templateId, $template->version, $institutionId, $assigneeUserId,
            $roleCode, strtoupper($scopeType), $scopeId ?? 'none', $scheduledDate,
        ]));

        return DB::transaction(function () use (
            $template, $templateId, $institutionId, $assigneeUserId, $roleCode,
            $scopeType, $scopeId, $scheduledFor, $dueAt, $templateSnapshot, $key
        ): int {
            $inserted = DB::table('tagore_operational_audit_instances')->insertOrIgnore([
                'template_id' => $templateId,
                'template_version' => (int) $template->version,
                'template_code' => $template->template_code,
                'institution_id' => $institutionId,
                'assignee_user_id' => $assigneeUserId,
                'assigned_role_code' => $roleCode,
                'scope_type' => strtoupper($scopeType),
                'scope_id' => $scopeId,
                'scheduled_for' => $scheduledFor->toDateString(),
                'due_at' => $dueAt,
                'status' => 'pending',
                'verification_status' => 'unverified',
                'idempotency_key' => $key,
                'template_snapshot_json' => json_encode($templateSnapshot, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $instance = DB::table('tagore_operational_audit_instances')->where('idempotency_key', $key)->first();
            if (!$instance) {
                throw new RuntimeException('The audit instance could not be created or recovered.');
            }

            if ($inserted === 1) {
                $this->appendInstanceEvent((int) $instance->id, null, 'generated', [
                    'template_code' => $template->template_code,
                    'template_version' => (int) $template->version,
                    'scheduled_for' => $scheduledFor->toDateString(),
                    'due_at' => $dueAt->toIso8601String(),
                ]);
            }

            return (int) $instance->id;
        });
    }

    public function submit(int $instanceId, int $actorUserId, array $responses): void
    {
        if ($responses === []) {
            throw new InvalidArgumentException('At least one audit response is required.');
        }

        DB::transaction(function () use ($instanceId, $actorUserId, $responses): void {
            $instance = DB::table('tagore_operational_audit_instances')->where('id', $instanceId)->lockForUpdate()->first();
            if (!$instance) {
                throw new InvalidArgumentException('Audit instance not found.');
            }
            if ((int) $instance->assignee_user_id !== $actorUserId) {
                throw new RuntimeException('Only the assigned user can submit this audit.');
            }
            if (!$this->hasActiveRoleAssignment($actorUserId, (string) $instance->assigned_role_code, (int) $instance->institution_id, now())) {
                throw new RuntimeException('The assignee no longer holds an active role assignment for this institution.');
            }
            if (!in_array($instance->status, ['pending', 'overdue'], true)) {
                throw new RuntimeException('This audit instance is no longer editable.');
            }

            $snapshot = json_decode((string) $instance->template_snapshot_json, true, 512, JSON_THROW_ON_ERROR);
            $templateControls = collect($snapshot['controls'] ?? [])->keyBy('key');
            if ($templateControls->isEmpty()) {
                throw new RuntimeException('The audit instance has no versioned control definition.');
            }

            $now = now();
            $seenControls = [];
            foreach ($responses as $response) {
                $controlKey = trim((string) ($response['control_key'] ?? ''));
                $evidenceClass = strtoupper((string) ($response['evidence_class'] ?? 'C'));
                $control = $templateControls->get($controlKey);
                $expectedClass = strtoupper((string) ($control['evidence_class'] ?? 'C'));
                if ($evidenceClass === 'A' || $expectedClass === 'A') {
                    throw new InvalidArgumentException('Class A is reserved for system-derived evidence and cannot be self-attested.');
                }
                if ($controlKey === '' || !$control || isset($seenControls[$controlKey])
                    || !in_array($expectedClass, ['B', 'C'], true)
                    || !in_array($evidenceClass, ['B', 'C'], true)
                    || $evidenceClass !== $expectedClass) {
                    throw new InvalidArgumentException('Responses must match a unique control and evidence class in the assigned template version.');
                }
                $seenControls[$controlKey] = true;
                DB::table('tagore_operational_audit_responses')->insert([
                    'instance_id' => $instanceId,
                    'control_key' => $controlKey,
                    'value_json' => json_encode($response['value'] ?? null, JSON_THROW_ON_ERROR),
                    'evidence_class' => $evidenceClass,
                    'verification_status' => 'unverified',
                    'source_table' => null,
                    'source_id' => null,
                    'submitted_by' => $actorUserId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('tagore_operational_audit_instances')->where('id', $instanceId)->update([
                'status' => Carbon::parse($instance->due_at)->isBefore($now) ? 'submitted_late' : 'submitted',
                'submitted_at' => $now,
                'updated_at' => $now,
            ]);
            $this->appendInstanceEvent($instanceId, $actorUserId, 'submitted', [
                'response_count' => count($responses),
                'submitted_late' => Carbon::parse($instance->due_at)->isBefore($now),
            ]);
        });
    }

    public function verify(int $instanceId, int $reviewerUserId, bool $passed, ?string $notes = null): void
    {
        DB::transaction(function () use ($instanceId, $reviewerUserId, $passed, $notes): void {
            $instance = DB::table('tagore_operational_audit_instances')->where('id', $instanceId)->lockForUpdate()->first();
            if (!$instance) {
                throw new InvalidArgumentException('Audit instance not found.');
            }
            if ((int) $instance->assignee_user_id === $reviewerUserId) {
                throw new RuntimeException('An audit submitter cannot independently verify their own submission.');
            }
            if (!$this->hasActiveReviewerAssignment($reviewerUserId, (int) $instance->institution_id, now())) {
                throw new RuntimeException('The reviewer must hold an active Owner, Principal or Coordinator role for this institution.');
            }
            if (!in_array($instance->status, ['submitted', 'submitted_late'], true)) {
                throw new RuntimeException('Only submitted audits can be independently verified.');
            }

            $now = now();
            DB::table('tagore_operational_audit_responses')->where('instance_id', $instanceId)->update([
                'verification_status' => $passed ? 'passed' : 'failed',
                'updated_at' => $now,
            ]);
            DB::table('tagore_operational_audit_instances')->where('id', $instanceId)->update([
                'status' => $passed ? 'verified' : 'failed',
                'verification_status' => $passed ? 'passed' : 'failed',
                'verified_by' => $reviewerUserId,
                'verified_at' => $now,
                'updated_at' => $now,
            ]);
            $this->appendInstanceEvent($instanceId, $reviewerUserId, $passed ? 'verified' : 'failed', [
                'notes' => $notes,
            ]);
        });
    }


    private function hasActiveRoleAssignment(int $userId, string $roleCode, int $institutionId, CarbonInterface $effectiveAt): bool
    {
        $query = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.code', strtoupper($roleCode))
            ->where('ur.status', 'active')
            ->where(fn ($builder) => $builder->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', $effectiveAt->toDateString()))
            ->where(fn ($builder) => $builder->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', $effectiveAt->toDateString()));

        if (strtoupper($roleCode) === 'OWNER') {
            $query->where(fn ($builder) => $builder->whereNull('ur.institution_id')->orWhere('ur.institution_id', $institutionId));
        } else {
            $query->where('ur.institution_id', $institutionId);
        }

        return $query->exists();
    }

    private function hasActiveReviewerAssignment(int $userId, int $institutionId, CarbonInterface $effectiveAt): bool
    {
        return DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('ur.status', 'active')
            ->whereIn('r.code', ['OWNER', 'PRINCIPAL', 'COORDINATOR'])
            ->where(fn ($builder) => $builder->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', $effectiveAt->toDateString()))
            ->where(fn ($builder) => $builder->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', $effectiveAt->toDateString()))
            ->where(function ($builder) use ($institutionId) {
                $builder->where(function ($owner) use ($institutionId) {
                    $owner->where('r.code', 'OWNER')->where(fn ($scope) => $scope->whereNull('ur.institution_id')->orWhere('ur.institution_id', $institutionId));
                })->orWhere(fn ($manager) => $manager->whereIn('r.code', ['PRINCIPAL', 'COORDINATOR'])->where('ur.institution_id', $institutionId));
            })
            ->exists();
    }

    private function appendInstanceEvent(int $instanceId, ?int $actorUserId, string $eventType, array $payload): void
    {
        DB::table('tagore_operational_audit_events')->insert([
            'instance_id' => $instanceId,
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
        ]);
    }
}
