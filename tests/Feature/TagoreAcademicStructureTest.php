<?php

namespace Tests\Feature;

use Tests\TestCase;

class TagoreAcademicStructureTest extends TestCase
{
    public function test_academic_structure_layer_exists(): void
    {
        foreach ([
            'app/Http/Controllers/Tagore/AcademicStructureController.php',
            'resources/views/tagore/academic-structure.blade.php',
            'database/migrations/2026_09_16_150000_create_tagore_academic_structure.php',
        ] as $path) $this->assertFileExists(base_path($path));
    }

    public function test_academic_structure_uses_gegok12_standard_and_enforces_institution_boundaries(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Tagore/AcademicStructureController.php'));
        $migration = file_get_contents(base_path('database/migrations/2026_09_16_150000_create_tagore_academic_structure.php'));
        foreach (['standards_link','academic_years','school_id','stream_id','institution_id','standard_link_id','owner'] as $needle) $this->assertStringContainsString($needle, $controller);
        foreach (['tagore_academic_streams','tagore_academic_sections','standards_link','academic_year_id'] as $needle) $this->assertStringContainsString($needle, $migration);
    }

    public function test_academic_structure_routes_are_present(): void
    {
        $routes = file_get_contents(base_path('routes/tagore.php'));
        foreach (['tagore.academic.structure','tagore.academic.stream','tagore.academic.section'] as $route) $this->assertStringContainsString($route, $routes);
    }
}
