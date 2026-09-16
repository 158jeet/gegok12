<?php
namespace Tests\Feature;
use Tests\TestCase;
class TagoreFeeImportTest extends TestCase
{
    public function test_fee_import_layer_files_exist(): void
    {
        $this->assertFileExists(base_path('app/Services/Tagore/LegacyFeeImportService.php'));
        $this->assertFileExists(base_path('app/Http/Controllers/Tagore/FeeImportController.php'));
        $this->assertFileExists(base_path('resources/views/tagore/fees/import.blade.php'));
        $this->assertFileExists(base_path('database/migrations/2026_09_16_120000_complete_tagore_fee_engine.php'));
        $this->assertFileExists(base_path('database/migrations/2026_09_16_121000_create_tagore_fee_import_tables.php'));
    }
    public function test_fee_import_routes_are_present(): void
    {
        $routes=file_get_contents(base_path('routes/tagore.php'));
        $this->assertStringContainsString('tagore.fees.import',$routes);
        $this->assertStringContainsString('tagore.fees.import.preview',$routes);
        $this->assertStringContainsString('tagore.fees.import.apply',$routes);
    }
}
