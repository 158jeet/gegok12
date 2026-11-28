<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreHrPayrollScopeTest extends TestCase
{
    public function test_teacher_role_in_another_institution_does_not_expand_principal_leave_or_payroll_authority(): void
    {
        $principalRoleId = (int) DB::table('tagore_roles')->where('code', 'PRINCIPAL')->value('id');
        $teacherRoleId = (int) DB::table('tagore_roles')->where('code', 'TEACHER')->value('id');
        $managerAssignment = DB::table('tagore_user_roles')
            ->where('role_id', $principalRoleId)->where('status', 'active')->whereNotNull('institution_id')->first();
        $this->assertNotNull($managerAssignment, 'Acceptance fixtures need a Principal assigned to an institution.');

        $principal = User::query()->findOrFail($managerAssignment->user_id);
        // Build a deterministic second institution instead of skipping when
        // the seeded database happens to contain only one institution.
        $groupId = DB::table('tagore_groups')->where('status', 'active')->whereNull('deleted_at')->value('id');
        $this->assertNotNull($groupId, 'The core Tagore group fixture must exist.');

        $otherSchool = School::factory()->create();
        $otherInstitutionId = DB::table('tagore_institutions')->insertGetId([
            'tagore_group_id' => $groupId,
            'school_id' => $otherSchool->id,
            'code' => 'HR-SCOPE-' . strtoupper(bin2hex(random_bytes(4))),
            'display_name' => 'HR Scope Test Institution',
            'type' => 'school',
            'status' => 'active',
            'settings_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherInstitution = DB::table('tagore_institutions')->where('id', $otherInstitutionId)->first();

        $teacher = User::factory()->for($otherSchool)->create(['usergroup_id' => 5]);
        $academicYearId = DB::table('academic_years')->insertGetId([
            'school_id' => $otherSchool->id,
            'name' => 'HR Scope Test Year',
            'description' => 'Synthetic acceptance fixture',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tagore_user_roles')->insert([
            'user_id' => $principal->id,
            'role_id' => $teacherRoleId,
            'institution_id' => $otherInstitution->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $leaveId = DB::table('teacher_leave_applications')->insertGetId([
            'school_id' => $otherInstitution->school_id,
            'academic_year_id' => $academicYearId,
            'standardLink_id' => null,
            'user_id' => $teacher->id,
            'from_date' => now()->addDays(10),
            'to_date' => now()->addDays(10),
            'remarks' => 'Cross-institution scope acceptance fixture',
            'leave_type_id' => null,
            'approved_by' => null,
            'approved_on' => null,
            'comments' => null,
            'session' => 'day',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($principal)->get(route('tagore.staff.leave'))
            ->assertOk()
            ->assertViewHas('applications', fn ($items) => $items->where('id', $leaveId)->isEmpty());

        $this->actingAs($principal)->get(route('tagore.payroll.index'))
            ->assertOk()
            ->assertViewHas('institutionIds', fn ($ids) => !in_array((int) $otherInstitution->id, $ids, true));

        $this->actingAs($principal)->post(route('tagore.staff.leave.decide', $leaveId), [
            'decision' => 'approved',
            'comments' => 'Must be denied outside manager scope',
        ])->assertNotFound();

        $this->assertDatabaseHas('teacher_leave_applications', [
            'id' => $leaveId,
            'status' => 'pending',
            'approved_by' => null,
        ]);
    }

    public function test_owner_retains_group_wide_payroll_visibility(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $allActiveInstitutionIds = DB::table('tagore_institutions')->where('status', 'active')
            ->whereNull('deleted_at')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->actingAs($owner)->get(route('tagore.payroll.index'))
            ->assertOk()
            ->assertViewHas('institutionIds', function ($ids) use ($allActiveInstitutionIds) {
                foreach ($allActiveInstitutionIds as $id) {
                    if (!in_array($id, $ids, true)) return false;
                }
                return true;
            });
    }
}
