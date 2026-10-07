<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class TagoreCoreModuleSmokeTest extends TestCase
{
    public function test_owner_can_open_all_core_erp_module_landing_pages(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();

        $routes = [
            'tagore.dashboard',
            'tagore.admin',
            'tagore.academic.structure',
            'tagore.admissions.index',
            'tagore.tasks.index',
            'tagore.fees.accounts',
            'tagore.fees.manage',
            'tagore.fees.editor',
            'tagore.fees.import',
            'tagore.payroll.index',
            'tagore.inventory.index',
            'tagore.transport.index',
            'tagore.communication.index',
            'tagore.reports.index',
            'tagore.security.index',
            'tagore.learning.index',
            'tagore.platform.index',
            'tagore.documents.index',
            'tagore.fees.wallet',
        ];

        foreach ($routes as $routeName) {
            $this->actingAs($owner)
                ->get(route($routeName))
                ->assertOk();
        }
    }

    public function test_core_module_routes_are_named_and_resolvable(): void
    {
        foreach ([
            'tagore.dashboard',
            'tagore.admin',
            'tagore.admissions.index',
            'tagore.tasks.index',
            'tagore.fees.accounts',
            'tagore.payroll.index',
            'tagore.inventory.index',
            'tagore.transport.index',
            'tagore.communication.index',
            'tagore.reports.index',
            'tagore.security.index',
            'tagore.learning.index',
            'tagore.platform.index',
            'tagore.documents.index',
        ] as $routeName) {
            $this->assertTrue(app('router')->getRoutes()->getByName($routeName) !== null, "Missing route: {$routeName}");
        }
    }
}
