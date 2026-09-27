<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreManagerDashboardTest extends TestCase
{
    public function test_manager_dashboard_shows_action_items_and_department_health(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $owner->school_id)->value('id');
        $departmentId = (int) DB::table('tagore_departments')->where('institution_id', $institutionId)->where('code', 'ACADEMIC')->value('id');

        DB::table('tagore_tasks')->insert([
            'institution_id' => $institutionId,
            'department_id' => $departmentId ?: null,
            'created_by' => $owner->id,
            'assigned_to' => null,
            'title' => 'Command center QA',
            'status' => 'blocked',
            'priority' => 'urgent',
            'progress' => 25,
            'due_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('tagore.dashboard', ['period' => 30]))
            ->assertOk()
            ->assertSee('Manager Command Center')
            ->assertSee('Command center QA')
            ->assertSee('Department health')
            ->assertSee('Employee Performance')
            ->assertSee('30-day workload trend')
            ->assertSee('Manager follow-up')
            ->assertSee('Workload balance')
            ->assertSee('Created in period')
            ->assertSee('Period completion')
            ->assertSee('Academic');
    }

    public function test_manager_can_open_department_performance_profile(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $owner->school_id)->value('id');
        $departmentId = (int) DB::table('tagore_departments')->where('institution_id', $institutionId)->where('code', 'ACADEMIC')->value('id');

        $this->actingAs($owner)
            ->get(route('tagore.department.profile', $departmentId))
            ->assertOk()
            ->assertSee('Academic')
            ->assertSee('Employee workload')
            ->assertSee('Pending follow-ups')
            ->assertSee('7-day workload trend');
    }

    public function test_non_manager_cannot_open_department_performance_profile(): void
    {
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $teacher->school_id)->value('id');
        $departmentId = (int) DB::table('tagore_departments')->where('institution_id', $institutionId)->where('code', 'ACADEMIC')->value('id');

        $this->actingAs($teacher)
            ->get(route('tagore.department.profile', $departmentId))
            ->assertForbidden();
    }


    public function test_manager_can_view_department_analytics_for_multiple_periods(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $departmentId = DB::table('tagore_departments')->where('code', 'ACADEMIC')->value('id');
        $this->assertNotNull($departmentId);

        $this->actingAs($owner)->get(route('tagore.department.profile', ['departmentId' => $departmentId, 'period' => 30]))
            ->assertOk()->assertSee('30-day workload trend')->assertSee('Created in period')->assertSee('Employee performance');

        $this->actingAs($owner)->get(route('tagore.department.profile', ['departmentId' => $departmentId, 'period' => 90]))
            ->assertOk()->assertSee('90-day workload trend');
    }

    public function test_non_manager_cannot_view_department_analytics(): void
    {
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->firstOrFail();
        $departmentId = DB::table('tagore_departments')->where('code', 'ACADEMIC')->value('id');
        $this->actingAs($teacher)->get(route('tagore.department.profile', ['departmentId' => $departmentId]))->assertForbidden();
    }

    public function test_owner_can_create_department_and_assign_staff(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $teacher = User::query()->where('usergroup_id', 5)->where('school_id', $owner->school_id)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $owner->school_id)->value('id');
        $code = 'QA' . now()->format('His');

        $this->actingAs($owner)->post(route('tagore.admin.department'), [
            'institution_id' => $institutionId,
            'name' => 'QA Operations',
            'code' => $code,
        ])->assertSessionHas('success', 'Department added.');

        $departmentId = (int) DB::table('tagore_departments')->where('institution_id', $institutionId)->where('code', $code)->value('id');
        $this->assertGreaterThan(0, $departmentId);

        $this->actingAs($owner)->post(route('tagore.admin.department.assign'), [
            'user_id' => $teacher->id,
            'department_id' => $departmentId,
            'designation' => 'QA Coordinator',
            'is_primary' => 1,
        ])->assertSessionHas('success', 'Department assignment saved.');

        $this->assertDatabaseHas('tagore_user_departments', [
            'user_id' => $teacher->id,
            'department_id' => $departmentId,
            'designation' => 'QA Coordinator',
            'is_primary' => 1,
            'status' => 'active',
        ]);
    }

    public function test_manager_can_review_staff_leave_application(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $schoolId = $owner->school_id;
        $academicYearId = (int) DB::table('academic_years')->where('school_id', $schoolId)->orderByDesc('id')->value('id');
        $leaveTypeId = DB::table('leave_types')->where('school_id', $schoolId)->whereNull('deleted_at')->value('id');

        $leaveId = DB::table('teacher_leave_applications')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'user_id' => $owner->id,
            'from_date' => now()->addDay(),
            'to_date' => now()->addDays(2),
            'leave_type_id' => $leaveTypeId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('tagore.staff.leave'))
            ->assertOk()->assertSee('Staff Leave Management')->assertSee('Pending decisions');

        $this->actingAs($owner)->post(route('tagore.staff.leave.decide', $leaveId), [
            'decision' => 'approved',
            'comments' => 'Approved by management.',
        ])->assertSessionHas('success', 'Leave approved.');

        $this->assertDatabaseHas('teacher_leave_applications', [
            'id' => $leaveId,
            'status' => 'approved',
            'approved_by' => $owner->id,
            'comments' => 'Approved by management.',
        ]);
    }

    public function test_non_manager_cannot_review_staff_leave(): void
    {
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->orderBy('id')->firstOrFail();

        $this->actingAs($teacher)->get(route('tagore.staff.leave'))->assertForbidden();
    }

}
