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
            'app/Console/Commands/Addon/InstallExamModule.php',
            'resources/views/tagore/examinations/index.blade.php',
            'resources/views/tagore/examinations/marks.blade.php',
            'resources/views/tagore/examinations/student-results.blade.php',
            'database/migrations/2026_10_30_090000_create_tagore_examination_engine.php',
        ] as $path) {
            $this->assertFileExists(base_path($path));
        }

        $migration = file_get_contents(base_path('database/migrations/2026_10_30_090000_create_tagore_examination_engine.php'));
        foreach (['tagore_exams', 'tagore_exam_subjects', 'tagore_exam_marks', 'tagore_exam_events', 'tagore_exam_mark_events', 'tagore_exam_grade_scales', 'standard_link_id', 'tagore_results', 'exam_id'] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        $installer = file_get_contents(base_path('app/Console/Commands/Addon/InstallExamModule.php'));
        $marksView = file_get_contents(base_path('resources/views/tagore/examinations/marks.blade.php'));
        $this->assertStringContainsString('data-absent-toggle', $marksView);
        $this->assertStringContainsString('data-marks-input', $marksView);

        $this->assertStringContainsString('2026_10_30_090000_create_tagore_examination_engine.php', $installer);
        $this->assertStringNotContainsString('gego-k12/exam', $installer);

        $controller = file_get_contents(base_path('app/Http/Controllers/Tagore/ExaminationController.php'));
        foreach (['MANAGERS', 'MARKS_ROLES', 'submitted', 'moderated', 'published', 'withdrawn', 'standard_link_id', 'tagore_results', 'class_teacher_links', 'studentResults', 'saveGradeScale', 'tagore_exam_grade_scales'] as $needle) {
            $this->assertStringContainsString($needle, $controller);
        }
    }

    public function test_parent_cannot_open_staff_examination_workspace(): void
    {
        $parent = User::query()->where('email', 'parent@tagore-demo.local')->firstOrFail();
        $this->actingAs($parent)->get(route('tagore.examinations.index'))->assertForbidden();
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
            'tagore.examinations.grades.save',
            'tagore.examinations.moderate',
            'tagore.examinations.publish',
            'tagore.examinations.withdraw',
            'tagore.results.student',
        ] as $name) {
            $this->assertNotNull(app('router')->getRoutes()->getByName($name), "Missing route: {$name}");
        }
    }

    public function test_owner_can_open_the_examination_workspace(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $this->actingAs($owner)->get(route('tagore.examinations.index'))->assertOk();
    }
}
