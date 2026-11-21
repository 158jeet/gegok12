<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Tagore\OperationalAuditService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreOperationalAuditFoundationTest extends TestCase
{
    public function test_daily_audit_generation_is_idempotent_and_keeps_generation_event_once(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $owner->school_id)->value('id');
        $principal = $this->assignedUser($institutionId, 'PRINCIPAL');
        $templateId = $this->activeTemplate($institutionId, $principal->id);

        $service = app(OperationalAuditService::class);
        $scheduledFor = now()->startOfDay();
        $dueAt = now()->endOfDay();

        $first = $service->generateInstance($templateId, $institutionId, $principal->id, 'PRINCIPAL', 'INSTITUTION', $institutionId, $scheduledFor, $dueAt, [
            'title' => 'Principal daily control',
            'controls' => [['key' => 'opening_checks', 'evidence_class' => 'A']],
        ]);
        $second = $service->generateInstance($templateId, $institutionId, $principal->id, 'PRINCIPAL', 'INSTITUTION', $institutionId, $scheduledFor, $dueAt, [
            'title' => 'Principal daily control',
            'controls' => [['key' => 'opening_checks', 'evidence_class' => 'A']],
        ]);

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::table('tagore_operational_audit_instances')->where('id', $first)->count());
        $this->assertSame(1, DB::table('tagore_operational_audit_events')->where('instance_id', $first)->where('event_type', 'generated')->count());
    }

    public function test_audit_submission_requires_assignee_and_independent_reviewer(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $teacher = User::query()->where('usergroup_id', 5)->where('school_id', $owner->school_id)->whereNull('deleted_at')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $owner->school_id)->value('id');
        $templateId = $this->activeTemplate($institutionId, $teacher->id, 'TEACHER');
        $instanceId = app(OperationalAuditService::class)->generateInstance(
            $templateId, $institutionId, $teacher->id, 'TEACHER', 'CLASS', 1,
            now()->startOfDay(), now()->endOfDay(), ['title' => 'Teacher daily control', 'controls' => [['key' => 'attendance']]]
        );

        $service = app(OperationalAuditService::class);
        $service->submit($instanceId, $teacher->id, [
            ['control_key' => 'attendance', 'value' => ['submitted' => true], 'evidence_class' => 'C'],
            ['control_key' => 'timetable_coverage', 'value' => ['periods' => 6], 'evidence_class' => 'B'],
        ]);

        $this->assertDatabaseHas('tagore_operational_audit_instances', [
            'id' => $instanceId,
            'status' => 'submitted',
            'verification_status' => 'unverified',
        ]);
        $this->assertSame(2, DB::table('tagore_operational_audit_responses')->where('instance_id', $instanceId)->count());

        try {
            $service->verify($instanceId, $teacher->id, true, 'Self review must be blocked.');
            $this->fail('The submitter must not verify their own audit.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('cannot independently verify', $exception->getMessage());
        }

        $student = User::query()->where('email', 'student@tagore-demo.local')->firstOrFail();
        try {
            $service->verify($instanceId, $student->id, true, 'Unassigned student review must be blocked.');
            $this->fail('Only an active scoped manager can verify an audit.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('active Owner, Principal or Coordinator role', $exception->getMessage());
        }

        $service->verify($instanceId, $owner->id, true, 'Independent sample review passed.');
        $this->assertDatabaseHas('tagore_operational_audit_instances', [
            'id' => $instanceId,
            'status' => 'verified',
            'verification_status' => 'passed',
            'verified_by' => $owner->id,
        ]);
        $this->assertDatabaseHas('tagore_operational_audit_events', [
            'instance_id' => $instanceId,
            'event_type' => 'verified',
            'actor_user_id' => $owner->id,
        ]);
    }

    public function test_audit_template_role_must_match_assigned_role(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $owner->school_id)->value('id');
        $templateId = $this->activeTemplate($institutionId, $owner->id);

        $this->expectException(\InvalidArgumentException::class);
        app(OperationalAuditService::class)->generateInstance(
            $templateId, $institutionId, $owner->id, 'TEACHER', 'INSTITUTION', $institutionId,
            now()->startOfDay(), now()->endOfDay(), ['title' => 'Wrong role']
        );
    }


    public function test_user_cannot_claim_system_derived_evidence_class(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $owner->school_id)->value('id');
        $principal = $this->assignedUser($institutionId, 'PRINCIPAL');
        $templateId = $this->activeTemplate($institutionId, $principal->id);
        $instanceId = app(OperationalAuditService::class)->generateInstance(
            $templateId, $institutionId, $principal->id, 'PRINCIPAL', 'INSTITUTION', $institutionId,
            now()->startOfDay(), now()->endOfDay(), ['title' => 'System evidence test']
        );

        try {
            app(OperationalAuditService::class)->submit($instanceId, $principal->id, [
                ['control_key' => 'fee_reconciliation', 'value' => ['balanced' => true], 'evidence_class' => 'A'],
            ]);
            $this->fail('A user must not self-assert system-derived evidence.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('reserved for system-derived', $exception->getMessage());
        }

        $this->assertDatabaseMissing('tagore_operational_audit_responses', [
            'instance_id' => $instanceId,
            'control_key' => 'fee_reconciliation',
        ]);
    }


    private function assignedUser(int $institutionId, string $roleCode): User
    {
        $userId = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.institution_id', $institutionId)
            ->where('ur.status', 'active')
            ->where('r.code', $roleCode)
            ->value('ur.user_id');

        return User::query()->findOrFail($userId);
    }

    private function activeTemplate(int $institutionId, int $createdBy, string $roleCode = 'PRINCIPAL'): int
    {
        return (int) DB::table('tagore_operational_audit_templates')->insertGetId([
            'institution_id' => $institutionId,
            'template_code' => 'QA-DAILY-' . bin2hex(random_bytes(5)),
            'version' => 1,
            'title' => 'Synthetic daily audit',
            'role_code' => $roleCode,
            'scope_type' => 'INSTITUTION',
            'cadence' => 'daily',
            'controls_json' => json_encode([['key' => 'opening_checks', 'evidence_class' => 'A']]),
            'status' => 'active',
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    public function test_assignee_cannot_submit_after_role_assignment_is_revoked(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $teacher = User::query()->where('usergroup_id', 5)->where('school_id', $owner->school_id)->whereNull('deleted_at')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $owner->school_id)->value('id');
        $templateId = $this->activeTemplate($institutionId, $teacher->id, 'TEACHER');
        $instanceId = app(OperationalAuditService::class)->generateInstance(
            $templateId, $institutionId, $teacher->id, 'TEACHER', 'CLASS', 1,
            now()->startOfDay(), now()->endOfDay(), ['title' => 'Revoked assignment test']
        );
        $teacherRoleId = (int) DB::table('tagore_roles')->where('code', 'TEACHER')->value('id');
        DB::table('tagore_user_roles')
            ->where('user_id', $teacher->id)
            ->where('institution_id', $institutionId)
            ->where('role_id', $teacherRoleId)
            ->update(['status' => 'inactive']);

        try {
            app(OperationalAuditService::class)->submit($instanceId, $teacher->id, [
                ['control_key' => 'attendance', 'value' => ['submitted' => true], 'evidence_class' => 'C'],
            ]);
            $this->fail('A revoked role assignment must not submit an audit.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('no longer holds an active role assignment', $exception->getMessage());
        } finally {
            DB::table('tagore_user_roles')
                ->where('user_id', $teacher->id)
                ->where('institution_id', $institutionId)
                ->where('role_id', $teacherRoleId)
                ->update(['status' => 'active']);
        }
    }

    public function test_owner_can_generate_audit_for_an_institution_bound_owner_assignment(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $owner->school_id)->value('id');
        $templateId = $this->activeTemplate($institutionId, $owner->id, 'OWNER');

        $instanceId = app(OperationalAuditService::class)->generateInstance(
            $templateId, $institutionId, $owner->id, 'OWNER', 'INSTITUTION', $institutionId,
            now()->startOfDay(), now()->endOfDay(), ['title' => 'Owner institution audit']
        );

        $this->assertDatabaseHas('tagore_operational_audit_instances', [
            'id' => $instanceId,
            'institution_id' => $institutionId,
            'assignee_user_id' => $owner->id,
            'assigned_role_code' => 'OWNER',
        ]);
    }

}
