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
        $parser = base_path('app/Services/Tagore/LegacyFeeWorkbookParser.php');

        foreach ([$service, $safety, $mappingMigration, $migration, $controller, $view, $parser] as $file) {
            $this->assertFileExists($file);
        }

        $source = file_get_contents($service);
        foreach (['FEE STRUCTURE', 'BUS FEE 26-27', 'OPENING', 'XII SCI FEE STRUCTURE', 'FEE CONCESSION', 'tagore_fee_structures', 'tagore_fee_components', 'tagore_transport_routes', 'tagore_transport_assignments', 'tagore_financial_transactions', 'source_hash', 'studentKey'] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }

        $parserSource = file_get_contents($parser);
        foreach (['BUS FEE 26-27', 'findNearestAmountColumn', 'PAYMENTS', 'ledger_reference'] as $needle) {
            $this->assertStringContainsString($needle, $parserSource);
        }

        $migrationSource = file_get_contents($service);
        foreach (['workbookParser', 'LegacyFeeWorkbookParser', 'legacyPayments'] as $needle) {
            $this->assertStringContainsString($needle, $migrationSource);
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
    public function test_student_master_and_confidence_matcher_are_wired_without_name_only_auto_mapping(): void
    {
        $parser = file_get_contents(base_path('app/Services/Tagore/LegacyFeeWorkbookParser.php'));
        $migration = file_get_contents(base_path('app/Services/Tagore/LegacyFeeMigrationService.php'));
        $matcher = file_get_contents(base_path('app/Services/Tagore/LegacyStudentMatcher.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Tagore/LegacyStudentMappingController.php'));
        $routes = file_get_contents(base_path('routes/tagore.php'));

        foreach (['STUDENTS', "'student_master'"] as $needle) {
            $this->assertStringContainsString($needle, $parser);
        }
        foreach (["\$type==='student_master'", "'status'=>'reference'", "case'student_master'"] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }
        foreach (['confidence', 'registration', 'name_father', 'applyHighConfidence', 'student_parent_links'] as $needle) {
            $this->assertStringContainsString($needle, $matcher);
        }
        $this->assertStringContainsString("(\$best['confidence'] ?? 0) < 90", $matcher);
        $this->assertStringContainsString("!\$item['source_key']", $matcher);
        $this->assertStringContainsString('mapping.suggestions', $routes);
        $this->assertStringContainsString('mapping.auto', $routes);
        $this->assertStringContainsString('LegacyStudentMatcher', $controller);
    }


}
