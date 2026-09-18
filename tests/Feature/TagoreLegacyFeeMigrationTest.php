<?php

namespace Tests\Feature;

use Tests\TestCase;

class TagoreLegacyFeeMigrationTest extends TestCase
{
    public function test_legacy_migration_engine_and_safety_schema_exist(): void
    {
        $service = base_path('app/Services/Tagore/LegacyFeeMigrationService.php');
        $safety = base_path('app/Services/Tagore/LegacyFeeImportSafetyService.php');
        $mappingMigration = base_path('database/migrations/2026_09_16_170000_create_tagore_legacy_student_mappings.php');
        $migration = base_path('database/migrations/2026_09_16_160000_extend_tagore_legacy_fee_import.php');
        $controller = base_path('app/Http/Controllers/Tagore/FeeImportController.php');
        $view = base_path('resources/views/tagore/fees/import.blade.php');

        foreach ([$service, $safety, $mappingMigration, $migration, $controller, $view] as $file) {
            $this->assertFileExists($file);
        }

        $source = file_get_contents($service);
        foreach (['FEE STRUCTURE', 'BUS FEE 26-27', 'OPENING', 'XII SCI FEE STRUCTURE', 'FEE CONCESSION', 'tagore_fee_structures', 'tagore_fee_components', 'tagore_transport_routes', 'tagore_transport_assignments', 'tagore_financial_transactions', 'source_hash', 'studentKey'] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }

        $safetySource = file_get_contents($safety);
        foreach (['tagore_legacy_student_mappings', 'numeric identifier requires an explicit legacy-student mapping', 'exceeds the net fee', 'needs_review'] as $needle) {
            $this->assertStringContainsString($needle, $safetySource);
        }
    }

    public function test_import_routes_use_the_safety_service(): void
    {
        $routes = file_get_contents(base_path('routes/tagore.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Tagore/FeeImportController.php'));

        $this->assertStringContainsString('tagore.fees.import.preview', $routes);
        $this->assertStringContainsString('tagore.fees.import.apply', $routes);
        $this->assertStringContainsString('LegacyFeeImportSafetyService', $controller);
        $this->assertStringContainsString('mimes:xlsx,xls,csv', $controller);
    }
}
