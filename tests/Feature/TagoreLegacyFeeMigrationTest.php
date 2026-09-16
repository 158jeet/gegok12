<?php

namespace Tests\Feature;

use Tests\TestCase;

class TagoreLegacyFeeMigrationTest extends TestCase
{
    public function test_legacy_migration_engine_and_transport_schema_exist(): void
    {
        $service = base_path('app/Services/Tagore/LegacyFeeMigrationService.php');
        $migration = base_path('database/migrations/2026_09_16_160000_extend_tagore_legacy_fee_import.php');
        $controller = base_path('app/Http/Controllers/Tagore/FeeImportController.php');
        $view = base_path('resources/views/tagore/fees/import.blade.php');
        foreach ([$service, $migration, $controller, $view] as $file) $this->assertFileExists($file);

        $source = file_get_contents($service);
        foreach (['FEE STRUCTURE','BUS FEE 26-27','OPENING','XII SCI FEE STRUCTURE','FEE CONCESSION','tagore_fee_structures','tagore_fee_components','tagore_transport_routes','tagore_transport_assignments','tagore_financial_transactions','source_hash','stable student identifier'] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }

    public function test_import_routes_use_the_new_migration_service(): void
    {
        $routes = file_get_contents(base_path('routes/tagore.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Tagore/FeeImportController.php'));
        $this->assertStringContainsString("tagore.fees.import.preview", $routes);
        $this->assertStringContainsString("tagore.fees.import.apply", $routes);
        $this->assertStringContainsString('LegacyFeeMigrationService', $controller);
        $this->assertStringContainsString('mimes:xlsx,xls,csv', $controller);
    }
}
