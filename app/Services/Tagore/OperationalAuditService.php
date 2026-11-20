<?php

namespace App\Services\Tagore;

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

        $roleCode = strtoupper(trim($roleCode));
        if ($roleCode === '' || $roleCode !== strtoupper((string) $template->role_code)) {
            throw new InvalidArgumentException('The assigned role must match the audit template role.');
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
            if (!in_array($instance->status, ['pending', 'overdue'], true)) {
                throw new RuntimeException('This audit instance is no longer editable.');
            }

            $now = now();
            foreach ($responses as $response) {
                $controlKey = trim((string) ($response['control_key'] ?? ''));
                $evidenceClass = strtoupper((string) ($response['evidence_class'] ?? 'C'));
                if ($controlKey === '' || !in_array($evidenceClass, ['A', 'B', 'C'], true)) {
                    throw new InvalidArgumentException('Each response needs a control key and evidence class A, B or C.');
                }

                DB::table('tagore_operational_audit_responses')->insert([
                    'instance_id' => $instanceId,
                    'control_key' => $controlKey,
                    'value_json' => json_encode($response['value'] ?? null, JSON_THROW_ON_ERROR),
                    'evidence_class' => $evidenceClass,
                    'verification_status' => $evidenceClass === 'A' ? 'system_pending' : 'unverified',
                    'source_table' => $response['source_table'] ?? null,
                    'source_id' => $response['source_id'] ?? null,
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
            if (!in_array($instance->status, ['submitted', 'submitted_late'], true)) {
                throw new RuntimeException('Only submitted audits can be independently verified.');
            }

            $now = now();
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
