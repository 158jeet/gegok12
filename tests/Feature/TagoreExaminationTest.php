<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class TagoreExaminationTest extends TestCase
{
    public function test_native_examination_engine_contract_is_present(): void
    {
        foreach ([
            'app/Http/Controllers/Tagore/ExaminationController.php',
            'resources/views/tagore/examinations/index.blade.php',
            'resources/views/tagore/examinations/marks.blade.php',
            'database/migrations/2026_10_30_090000_create_tagore_examination_engine.php',
        ] as $path) {
            $this->assertFileExists(base_path($path));
        }

        $migration = file_get_contents(base_path('database/migrations/2026_10_30_090000_create_tagore_examination_engine.php'));
        foreach (['tagore_exams', 'tagore_exam_subjects', 'tagore_exam_marks', 'tagore_exam_events', 'standard_link_id', 'tagore_results', 'exam_id'] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        $controller = file_get_contents(base_path('app/Http/Controllers/Tagore/ExaminationController.php'));
        foreach (['MANAGERS', 'MARKS_ROLES', 'submitted', 'moderated', 'published', 'withdrawn', 'standard_link_id', 'tagore_results'] as $needle) {
            $this->assertStringContainsString($needle, $controller);
        }
    }

    public function test_examination_routes_are_named_and_registered(): void
    {
        foreach ([
            'tagore.examinations.index',
            'tagore.examinations.store',
            'tagore.examinations.subjects.store',
            'tagore.examinations.marks',
            'tagore.examinations.marks.save',
            'tagore.examinations.submit',
            'tagore.examinations.moderate',
            'tagore.examinations.publish',
            'tagore.examinations.withdraw',
        ] as $name) {
            $this->assertNotNull(app('router')->getRoutes()->getByName($name), "Missing route: {$name}");
        }
    }

    public function test_owner_can_open_the_examination_workspace(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $this->actingAs($owner)->get(route('tagore.examinations.index'))->assertOk();
    }
}
