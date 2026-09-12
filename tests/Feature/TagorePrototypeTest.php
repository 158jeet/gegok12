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
    }
}
