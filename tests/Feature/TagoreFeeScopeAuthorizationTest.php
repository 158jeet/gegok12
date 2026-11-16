<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('e2e')]
class TagoreFeeScopeAuthorizationTest extends TestCase
{
    private function academicFixture(): array
    {
        $student = User::query()->where('email', 'student@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $schoolId = (int) $student->school_id;
        $now = now();

        $yearId = DB::table('academic_years')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Fee Scope Test '.uniqid(),
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $standardId = DB::table('standards')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Fee Scope Test Grade',
            'slug' => 'fee-scope-'.uniqid(),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sectionId = DB::table('sections')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Fee Scope Test Section',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $standardLinkId = DB::table('standards_link')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'class_teacher_id' => $student->id,
            'standard_id' => $standardId,
            'section_id' => $sectionId,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('student_academics')->insert([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'user_id' => $student->id,
            'standardLink_id' => $standardLinkId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$student, $schoolId, $yearId, $standardLinkId, $standardId, $sectionId, $now];
    }

    public function test_coordinator_can_only_view_fee_status_for_explicitly_scoped_class(): void
    {
        $coordinator = User::query()->where('email', 'coordinator@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        [$student, , , $standardLinkId] = $this->academicFixture();
        $this->actingAs($coordinator)->get(route('tagore.fees.student', $student->id))->assertForbidden();

        $permissionId = DB::table('tagore_permissions')->where('code', 'fee.view')->value('id');
        $this->assertNotNull($permissionId);
        DB::table('tagore_user_scopes')->insert([
            'user_id' => $coordinator->id,
            'scope_type' => 'standard_link',
            'scope_id' => $standardLinkId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($coordinator)->get(route('tagore.fees.student', $student->id))->assertOk();
    }

    public function test_teacher_can_only_view_fee_status_for_an_assigned_class(): void
    {
        $teacher = User::query()->where('email', 'teacher@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        [$student, $schoolId, $yearId, $standardLinkId, $standardId, $sectionId, $now] = $this->academicFixture();
        $this->actingAs($teacher)->get(route('tagore.fees.student', $student->id))->assertForbidden();

        $subjectId = DB::table('subjects')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'standard_id' => $standardId,
            'section_id' => $sectionId,
            'name' => 'Fee Scope Test Subject',
            'type' => 'core',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('class_teacher_links')->insert([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'standardLink_id' => $standardLinkId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacher->id,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($teacher)->get(route('tagore.fees.student', $student->id))->assertOk();
    }

    public function test_owner_can_grant_and_revoke_coordinator_fee_scope_with_audit(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $coordinator = User::query()->where('email', 'coordinator@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        [, , , $standardLinkId] = $this->academicFixture();

        $this->actingAs($owner)->post(route('tagore.admin.fee-scopes.grant'), [
            'user_id' => $coordinator->id,
            'standard_link_id' => $standardLinkId,
            'reason' => 'Assigned fee follow-up for this class',
        ])->assertRedirect();

        $permissionId = DB::table('tagore_permissions')->where('code', 'fee.view')->value('id');
        $scope = DB::table('tagore_user_scopes')->where('user_id', $coordinator->id)
            ->where('scope_type', 'standard_link')->where('scope_id', $standardLinkId)
            ->where('permission_id', $permissionId)->first();
        $this->assertNotNull($scope);
        $this->assertDatabaseHas('tagore_user_admin_audits', [
            'actor_id' => $owner->id, 'target_user_id' => $coordinator->id, 'action' => 'fee_scope_granted',
        ]);

        $this->actingAs($owner)->delete(route('tagore.admin.fee-scopes.revoke', $scope->id), [
            'reason' => 'Coordinator assignment has ended',
        ])->assertRedirect();

        $this->assertDatabaseMissing('tagore_user_scopes', ['id' => $scope->id]);
        $this->assertDatabaseHas('tagore_user_admin_audits', [
            'actor_id' => $owner->id, 'target_user_id' => $coordinator->id, 'action' => 'fee_scope_revoked',
        ]);
    }

    public function test_non_owner_cannot_grant_or_revoke_fee_scopes(): void
    {
        $coordinator = User::query()->where('email', 'coordinator@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        [, , , $standardLinkId] = $this->academicFixture();

        $this->actingAs($coordinator)->post(route('tagore.admin.fee-scopes.grant'), [
            'user_id' => $coordinator->id,
            'standard_link_id' => $standardLinkId,
            'reason' => 'Unauthorized scope attempt',
        ])->assertForbidden();
    }
}
