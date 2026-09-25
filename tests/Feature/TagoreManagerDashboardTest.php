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
            ->get(route('tagore.dashboard'))
            ->assertOk()
            ->assertSee('Manager Command Center')
            ->assertSee('Command center QA')
            ->assertSee('Department health')
            ->assertSee('Employee Performance')
            ->assertSee('7-day workload trend')
            ->assertSee('Academic');
    }
}
