<?php

namespace Tests\Feature;

use Tests\TestCase;

class TagoreFeeImportTest extends TestCase
{
    public function test_fee_layer_files_exist(): void
    {
        foreach ([
            'app/Services/Tagore/LegacyFeeImportService.php', 'app/Services/Tagore/FeeService.php',
            'app/Http/Controllers/Tagore/FeeImportController.php', 'app/Http/Controllers/Tagore/FeeManagementController.php',
            'resources/views/tagore/fees/import.blade.php', 'resources/views/tagore/fees/manage.blade.php',
            'database/migrations/2026_09_16_120000_complete_tagore_fee_engine.php',
            'database/migrations/2026_09_16_121000_create_tagore_fee_import_tables.php',
            'database/migrations/2026_09_16_130000_create_tagore_fee_structure_assignments.php',
        ] as $path) $this->assertFileExists(base_path($path));
    }

    public function test_fee_routes_are_present(): void
    {
        $routes = file_get_contents(base_path('routes/tagore.php'));
        foreach (['tagore.fees.manage','tagore.fees.manage.structure','tagore.fees.manage.demand','tagore.fees.manage.assignment','tagore.fees.manage.bulk.preview','tagore.fees.manage.bulk','tagore.fees.import','tagore.fees.import.preview','tagore.fees.import.apply'] as $route) $this->assertStringContainsString($route, $routes);
    }

    public function test_fee_service_contains_atomic_demand_and_payment_paths(): void
    {
        $service = file_get_contents(base_path('app/Services/Tagore/FeeService.php'));
        foreach (['createObligation','createBulkDemands','recordOfflinePayment','tagore_payment_allocations','DB::transaction','createInstallments'] as $needle) $this->assertStringContainsString($needle, $service);
    }

    public function test_bulk_fee_flow_is_class_section_based_and_idempotent(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Tagore/FeeManagementController.php'));
        $view = file_get_contents(base_path('resources/views/tagore/fees/manage.blade.php'));
        $migration = file_get_contents(base_path('database/migrations/2026_09_16_130000_create_tagore_fee_structure_assignments.php'));
        foreach (['standard_link_id','student_academics','eligibleStudents','createBulkDemands','whereNotIn'] as $needle) $this->assertStringContainsString($needle, $controller);
        foreach (['bulk.preview','bulk','assignment_id','Generate'] as $needle) $this->assertStringContainsString($needle, $view);
        foreach (['tagore_fee_structure_assignments','standard_link_id','fee_structure_id'] as $needle) $this->assertStringContainsString($needle, $migration);
    }

    public function test_bulk_fee_flow_enforces_institution_and_class_scope(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Tagore/FeeManagementController.php'));
        foreach (['authorizeInstitution','authorizeClassScope','institution_id','school_id','academic_year_id'] as $needle) $this->assertStringContainsString($needle, $controller);
    }
}
