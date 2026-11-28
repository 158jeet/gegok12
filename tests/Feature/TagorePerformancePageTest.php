<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class TagorePerformancePageTest extends TestCase
{
    public function test_manager_can_open_staff_performance_dashboard(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('tagore.performance.index'))
            ->assertOk()
            ->assertSee('Staff performance')
            ->assertSee('Reporting window')
            ->assertSee('Staff workload');
    }

    public function test_teacher_cannot_open_manager_performance_dashboard(): void
    {
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->orderBy('id')->firstOrFail();

        $this->actingAs($teacher)
            ->get(route('tagore.performance.index'))
            ->assertForbidden();
    }

    public function test_performance_dashboard_rejects_unsupported_reporting_window(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('tagore.performance.index', ['days' => 14]))
            ->assertStatus(422);
    }
}