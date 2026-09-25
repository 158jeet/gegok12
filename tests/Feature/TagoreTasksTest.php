<?php

namespace Tests\Feature;

use App\Models\User;
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
}
