<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class TagoreCashierWorkspaceTest extends TestCase
{
    public function test_cashier_counter_is_available_to_finance_operators_and_not_read_only_or_parent_roles(): void
    {
        foreach ([
            'owner@tagore-demo.local' => 200,
            'principal@tagore-demo.local' => 200,
            'accounts@tagore-demo.local' => 200,
            'coordinator@tagore-demo.local' => 403,
            'teacher@tagore-demo.local' => 403,
            'fees@tagore-demo.local' => 403,
            'parent@tagore-demo.local' => 403,
            'student@tagore-demo.local' => 403,
        ] as $email => $status) {
            $user = User::query()->where('email', $email)->whereNull('deleted_at')->firstOrFail();

            $this->actingAs($user)
                ->get('/tagore/cashier')
                ->assertStatus($status);
        }
    }

    public function test_cashier_counter_supports_student_search_and_renders_collection_controls(): void
    {
        $accounts = User::query()
            ->where('email', 'accounts@tagore-demo.local')
            ->whereNull('deleted_at')
            ->firstOrFail();

        $this->actingAs($accounts)
            ->get('/tagore/cashier?q=acceptance')
            ->assertOk()
            ->assertSee('Cashier counter')
            ->assertSee('Search by student name', false)
            ->assertSee('Record & email receipt', false);
    }

    public function test_read_only_coordinator_cannot_post_a_payment_directly(): void
    {
        $coordinator = User::query()
            ->where('email', 'coordinator@tagore-demo.local')
            ->whereNull('deleted_at')
            ->firstOrFail();

        $this->actingAs($coordinator)
            ->post('/tagore/accounts/fees/student/1/payment', [
                'amount' => 1,
                'payment_mode' => 'cash',
            ])
            ->assertForbidden();
    }

    public function test_cashier_route_is_named_and_resolvable(): void
    {
        $route = app('router')->getRoutes()->getByName('tagore.cashier.index');

        $this->assertNotNull($route);
        $this->assertSame('tagore/cashier', $route->uri());
    }
}
