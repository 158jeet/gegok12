<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreTasksTest extends TestCase
{
    public function test_owner_can_create_and_update_task(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->value('id');

        $this->actingAs($owner)->post(route('tagore.tasks.store'), [
            'institution_id' => $institutionId,
            'title' => 'QA task',
            'description' => 'Automated task workflow',
            'priority' => 'high',
            'due_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect();

        $taskId = (int) DB::table('tagore_tasks')->where('title', 'QA task')->max('id');
        $this->assertGreaterThan(0, $taskId);

        $this->actingAs($owner)->patch(route('tagore.tasks.update', $taskId), [
            'status' => 'completed',
            'progress' => 100,
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_tasks', [
            'id' => $taskId,
            'status' => 'completed',
            'progress' => 100,
        ]);

        $this->assertDatabaseHas('tagore_task_events', ['task_id' => $taskId, 'event_type' => 'created']);
        $this->assertDatabaseHas('tagore_task_events', ['task_id' => $taskId, 'event_type' => 'status_changed']);
        $this->assertDatabaseHas('tagore_task_events', ['task_id' => $taskId, 'event_type' => 'progress_changed']);
    }

    public function test_task_index_stays_within_a_small_query_budget(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $queries = 0;
        DB::listen(static function () use (&$queries): void { $queries++; });

        $this->actingAs($owner)->get(route('tagore.tasks.index'))->assertOk();

        $this->assertLessThan(35, $queries, "Task index executed {$queries} SQL queries.");
    }


    public function test_manager_can_see_employee_workload_and_filter_institution(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->value('id');
        $employee = User::query()
            ->where('school_id', $owner->school_id)
            ->where('usergroup_id', 5)
            ->where('id', '!=', $owner->id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        DB::table('tagore_tasks')->insert([
            'institution_id' => $institutionId,
            'created_by' => $owner->id,
            'assigned_to' => $employee->id,
            'title' => 'Manager workload QA',
            'status' => 'in_progress',
            'priority' => 'high',
            'progress' => 50,
            'due_at' => now()->addHours(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($owner)
            ->get(route('tagore.tasks.index', ['institution_id' => $institutionId]))
            ->assertOk();

        $response->assertSee('Institution workload');
        $response->assertSee($employee->name);
        $response->assertSee('Manager workload QA');
    }


    public function test_manager_can_reassign_task_and_filter_by_employee(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->where('id', '!=', $owner->id)->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $teacher->school_id)->value('id');
        DB::table('tagore_tasks')->insert([
            'institution_id'=>$institutionId,'created_by'=>$owner->id,'assigned_to'=>null,
            'title'=>'Reassignment QA','status'=>'open','priority'=>'normal','progress'=>0,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $taskId = (int) DB::table('tagore_tasks')->where('title','Reassignment QA')->max('id');

        $this->actingAs($owner)->patch(route('tagore.tasks.update', $taskId), [
            'status'=>'in_progress','progress'=>25,'assigned_to'=>$teacher->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_tasks', ['id'=>$taskId,'assigned_to'=>$teacher->id,'progress'=>25]);
        $this->assertDatabaseHas('tagore_task_events', ['task_id' => $taskId, 'event_type' => 'reassigned']);

        $this->actingAs($owner)
            ->get(route('tagore.tasks.index', ['institution_id'=>$institutionId,'assigned_to'=>$teacher->id]))
            ->assertOk()
            ->assertSee('Reassignment QA');
    }


    public function test_manager_can_view_department_workload_and_department_is_inferred_from_assignee(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->where('id', '!=', $owner->id)->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $teacher->school_id)->value('id');
        $departmentId = (int) DB::table('tagore_user_departments as ud')
            ->join('tagore_departments as d', 'd.id', '=', 'ud.department_id')
            ->where('ud.user_id', $teacher->id)->where('ud.status', 'active')->where('ud.is_primary', true)
            ->where('d.institution_id', $institutionId)->value('d.id');

        $this->actingAs($owner)->post(route('tagore.tasks.store'), [
            'institution_id' => $institutionId,
            'assigned_to' => $teacher->id,
            'title' => 'Department inference QA',
            'priority' => 'normal',
        ])->assertRedirect();

        $task = DB::table('tagore_tasks')->where('title', 'Department inference QA')->first();
        $this->assertSame($departmentId, (int) $task->department_id);

        $this->actingAs($owner)
            ->get(route('tagore.tasks.index', ['institution_id' => $institutionId, 'department_id' => $departmentId]))
            ->assertOk()
            ->assertSee('Department workload')
            ->assertSee('Department inference QA');
    }

    public function test_parent_cannot_access_tasks(): void
    {
        $parent = User::query()->where('usergroup_id', 7)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        $this->actingAs($parent)->get(route('tagore.tasks.index'))->assertForbidden();
    }

    public function test_task_update_is_scoped_to_creator_or_assignee(): void
    {
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $teacher->school_id)->value('id');

        $otherTeacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->where('id','!=',$teacher->id)->where('school_id',$teacher->school_id)->first();
        if (!$otherTeacher) $this->markTestSkipped('Prototype seed has only one teacher in this school.');

        $taskId = DB::table('tagore_tasks')->insertGetId([
            'institution_id'=>$institutionId,'created_by'=>$otherTeacher->id,'assigned_to'=>$otherTeacher->id,
            'title'=>'Scoped QA','status'=>'open','priority'=>'normal','progress'=>0,'created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->actingAs($teacher)->patch(route('tagore.tasks.update',$taskId), [
            'status'=>'completed','progress'=>100,
        ])->assertForbidden();
    }

    public function test_manager_can_record_employee_review_and_review_is_audited(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->where('id', '!=', $owner->id)->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $teacher->school_id)->value('id');

        $taskId = DB::table('tagore_tasks')->insertGetId([
            'institution_id' => $institutionId,
            'created_by' => $owner->id,
            'assigned_to' => $teacher->id,
            'title' => 'Review QA',
            'status' => 'in_progress',
            'priority' => 'high',
            'progress' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)->post(route('tagore.tasks.review', $taskId), [
            'review_type' => 'performance',
            'outcome' => 'needs_attention',
            'notes' => 'Follow up on the pending deliverable.',
            'action_required' => '1',
            'follow_up_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_employee_reviews', [
            'task_id' => $taskId,
            'employee_id' => $teacher->id,
            'manager_id' => $owner->id,
            'outcome' => 'needs_attention',
            'action_required' => 1,
        ]);
        $this->assertDatabaseHas('tagore_task_events', [
            'task_id' => $taskId,
            'event_type' => 'manager_reviewed',
        ]);
    }

    public function test_teacher_cannot_record_manager_review(): void
    {
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $teacher->school_id)->value('id');
        $taskId = DB::table('tagore_tasks')->insertGetId([
            'institution_id' => $institutionId,
            'created_by' => $teacher->id,
            'assigned_to' => $teacher->id,
            'title' => 'Review authorization QA',
            'status' => 'open',
            'priority' => 'normal',
            'progress' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($teacher)->post(route('tagore.tasks.review', $taskId), [
            'review_type' => 'follow_up',
            'outcome' => 'note',
            'notes' => 'Should not be accepted.',
        ])->assertForbidden();

        $this->assertDatabaseMissing('tagore_employee_reviews', ['task_id' => $taskId, 'manager_id' => $teacher->id]);
    }


    public function test_manager_follow_up_appears_on_dashboard_and_can_be_closed(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->where('id', '!=', $owner->id)->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $teacher->school_id)->value('id');

        $taskId = DB::table('tagore_tasks')->insertGetId([
            'institution_id' => $institutionId,
            'created_by' => $owner->id,
            'assigned_to' => $teacher->id,
            'title' => 'Follow-up dashboard QA',
            'status' => 'in_progress',
            'priority' => 'high',
            'progress' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reviewId = DB::table('tagore_employee_reviews')->insertGetId([
            'institution_id' => $institutionId,
            'employee_id' => $teacher->id,
            'manager_id' => $owner->id,
            'task_id' => $taskId,
            'review_type' => 'follow_up',
            'outcome' => 'action_required',
            'notes' => 'Complete pending deliverable.',
            'action_required' => true,
            'follow_up_at' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('tagore.dashboard'))
            ->assertOk()
            ->assertSee('Pending manager follow-ups')
            ->assertSee('Follow-up dashboard QA')
            ->assertSee('Overdue');

        $this->actingAs($owner)->patch(route('tagore.reviews.complete', $reviewId))
            ->assertRedirect();

        $this->assertDatabaseHas('tagore_employee_reviews', [
            'id' => $reviewId,
            'action_required' => 1,
        ]);
        $this->assertDatabaseHas('tagore_task_events', [
            'task_id' => $taskId,
            'event_type' => 'follow_up_completed',
        ]);
        $this->assertDatabaseMissing('tagore_employee_reviews', [
            'id' => $reviewId,
            'completed_at' => null,
        ]);
    }



    public function test_manager_can_open_employee_work_profile(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->where('id', '!=', $owner->id)->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $teacher->school_id)->value('id');

        $this->actingAs($owner)->get(route('tagore.tasks.employee', $teacher->id))
            ->assertOk()
            ->assertSee($teacher->name)
            ->assertSee('Assigned work')
            ->assertSee('Manager review history')
            ->assertSee('Activity timeline')
            ->assertSee('7 / 30 / 90-day trend')
            ->assertSee('Workload position');

        $this->assertTrue(DB::table('tagore_user_roles')->where('user_id', $teacher->id)->where('institution_id', $institutionId)->where('status','active')->exists());
    }

    public function test_employee_work_profile_rejects_out_of_scope_user(): void
    {
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        $this->actingAs($teacher)->get(route('tagore.tasks.employee', $teacher->id))->assertForbidden();
    }


    public function test_manager_can_create_and_pause_recurring_task_template(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $owner->school_id)->value('id');

        $this->actingAs($owner)->post(route('tagore.task-templates.store'), [
            'institution_id' => $institutionId,
            'title' => 'Daily attendance review',
            'priority' => 'normal',
            'frequency' => 'daily',
            'run_at' => '23:59',
            'due_after_minutes' => 120,
        ])->assertRedirect();

        $template = DB::table('tagore_task_templates')->where('title','Daily attendance review')->latest('id')->first();
        $this->assertNotNull($template);
        $this->assertTrue((bool)$template->active);
        $this->assertNotNull($template->next_run_at);

        $this->actingAs($owner)->patch(route('tagore.task-templates.toggle', $template->id))
            ->assertRedirect();

        $this->assertDatabaseHas('tagore_task_templates', ['id'=>$template->id,'active'=>0]);
    }

    public function test_recurring_task_command_generates_due_task_and_advances_template(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $owner->school_id)->value('id');

        $templateId = DB::table('tagore_task_templates')->insertGetId([
            'institution_id'=>$institutionId,'created_by'=>$owner->id,'title'=>'Generate routine QA',
            'priority'=>'high','frequency'=>'daily','run_at'=>'09:00:00','next_run_at'=>now()->subMinute(),
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);

        Artisan::call('tagore:generate-recurring-tasks');

        $task = DB::table('tagore_tasks')->where('title','Generate routine QA')->latest('id')->first();
        $this->assertNotNull($task);
        $this->assertDatabaseHas('tagore_task_events',['task_id'=>$task->id,'event_type'=>'created_from_template']);
        $advanced = DB::table('tagore_task_templates')->where('id',$templateId)->first();
        $this->assertNotNull($advanced->last_generated_at);
        $this->assertNotNull($advanced->next_run_at);
        $this->assertGreaterThan(now()->subSecond()->timestamp, strtotime($advanced->next_run_at));
    }

}
