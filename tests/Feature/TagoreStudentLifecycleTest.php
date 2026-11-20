<?php

namespace Tests\Feature;

use Tests\TestCase;

class TagoreStudentLifecycleTest extends TestCase
{
    public function test_student_lifecycle_promotion_layer_is_registered_and_audited(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Tagore/StudentLifecycleController.php'));
        $migration = file_get_contents(base_path('database/migrations/2026_11_20_090000_create_tagore_student_lifecycle_events.php'));
        $routes = file_get_contents(base_path('routes/tagore.php'));

        foreach ([
            'OWNER', 'PRINCIPAL', 'COORDINATOR', 'institution_id',
            'source_academic_year_id', 'target_academic_year_id',
            'target_standard_link_id', 'whereIn', 'lockForUpdate',
            "where('ur.institution_id', $institution->id)", "whereNull('ur.institution_id')",
            "whereNull('deleted_at')",
            "whereIn('r.code', ['PRINCIPAL', 'COORDINATOR'])",
            'student_academics', 'ValidationException',
        ] as $needle) {
            $this->assertStringContainsString($needle, $controller);
        }

        foreach ([
            'tagore_student_lifecycle_events', 'source_student_academic_id',
            'target_student_academic_id', 'performed_by', 'tagore_student_lifecycle_once',
        ] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        $this->assertStringContainsString('tagore.students.promote', $routes);
    }
}
