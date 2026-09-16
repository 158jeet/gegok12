<?php

namespace Tests\Feature;

use Tests\TestCase;

class TagoreFeeImportTest extends TestCase
{
    public function test_fee_layer_files_exist(): void
    {
        foreach ([
            'app/Services/Tagore/LegacyFeeImportService.php',
            'app/Http/Controllers/Tagore/FeeImportController.php',
            'app/Http/Controllers/Tagore/FeeManagementController.php',
            'resources/views/tagore/fees/import.blade.php',
            'resources/views/tagore/fees/manage.blade.php',
            'database/migrations/2026_09_16_120000_complete_tagore_fee_engine.php',
            'database/migrations/2026_09_16_121000_create_tagore_fee_import_tables.php',
        ] as $path) {
            $this->assertFileExists(base_path($path));
        }
    }

    public function test_fee_routes_are_present(): void
    {
        $routes = file_get_contents(base_path('routes/tagore.php'));
        foreach ([
            'tagore.fees.manage',
            'tagore.fees.manage.structure',
            'tagore.fees.manage.demand',
            'tagore.fees.import',
            'tagore.fees.import.preview',
            'tagore.fees.import.apply',
        ] as $route) {
            $this->assertStringContainsString($route, $routes);
        }
    }

    public function test_fee_service_contains_atomic_demand_and_payment_paths(): void
    {
        $service = file_get_contents(base_path('app/Services/Tagore/FeeService.php'));
        $this->assertStringContainsString('createObligation', $service);
        $this->assertStringContainsString('recordOfflinePayment', $service);
        $this->assertStringContainsString('tagore_payment_allocations', $service);
        $this->assertStringContainsString('DB::transaction', $service);
    }
}
