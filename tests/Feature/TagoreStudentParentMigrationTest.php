<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class TagoreStudentParentMigrationTest extends TestCase
{
    public function test_migration_service_and_controller_exist(): void
    {
        $this->assertFileExists(base_path('app/Services/Tagore/StudentParentMigrationService.php'));
        $this->assertFileExists(base_path('app/Http/Controllers/Tagore/StudentParentMigrationController.php'));
        $this->assertFileExists(base_path('resources/views/tagore/student-parent-migration.blade.php'));
    }

    public function test_service_uses_existing_gegok12_relationships_and_is_idempotent(): void
    {
        $service = file_get_contents(base_path('app/Services/Tagore/StudentParentMigrationService.php'));
        $this->assertStringContainsString("where('usergroup_id', 6)", $service);
        $this->assertStringContainsString("where('usergroup_id', 7)", $service);
        $this->assertStringContainsString("student_parent_links", $service);
        $this->assertStringContainsString("tagore_parent_students", $service);
        $this->assertStringContainsString('updateOrInsert', $service);
        $this->assertStringContainsString('DB::transaction', $service);
    }

    public function test_routes_are_registered(): void
    {
        $routes = file_get_contents(base_path('routes/tagore.php'));
        $this->assertStringContainsString("tagore.migration.student-parent", $routes);
        $this->assertStringContainsString("tagore.migration.student-parent.sync", $routes);
        $this->assertStringContainsString('StudentParentMigrationController', $routes);
    }
}
