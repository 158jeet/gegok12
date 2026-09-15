<?php

namespace Tests\Feature;

use Tests\TestCase;

class TagorePrototypeTest extends TestCase
{
    public function test_tagore_prototype_files_are_registered(): void
    {
        $this->assertFileExists(base_path('routes/tagore.php'));
        $this->assertFileExists(base_path('database/seeders/TagorePrototypeSeeder.php'));
        $this->assertFileExists(base_path('app/Http/Controllers/Tagore/DashboardController.php'));
        $this->assertFileExists(base_path('app/Services/Tagore/FeeService.php'));
    }

    public function test_fee_ledger_contains_the_required_real_world_layers(): void
    {
        $migration = base_path('database/migrations/2026_09_15_090000_extend_tagore_fee_ledger.php');
        $this->assertFileExists($migration);

        $source = file_get_contents($migration);
        foreach ([
            'tagore_fee_obligation_items',
            'tagore_fee_concessions',
            'tagore_transport_routes',
            'tagore_student_transport',
            'tagore_fee_opening_balances',
            'tagore_payment_allocations',
            'receipt_no',
            'payment_mode',
            'reference_number',
        ] as $required) {
            $this->assertStringContainsString($required, $source);
        }
    }

    public function test_fee_service_supports_obligations_and_allocated_payments(): void
    {
        $service = app(\App\Services\Tagore\FeeService::class);

        $this->assertTrue(method_exists($service, 'createObligation'));
        $this->assertTrue(method_exists($service, 'recordOfflinePayment'));
    }
}
