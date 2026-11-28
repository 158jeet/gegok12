<?php

namespace Tests\Feature;

use App\Http\Controllers\Tagore\ExaminationController;
use PHPUnit\Framework\TestCase;

class TagoreExaminationRoleBoundaryTest extends TestCase
{
    public function test_technical_admin_has_no_academic_management_or_result_approval_authority(): void
    {
        $controller = new \ReflectionClass(ExaminationController::class);

        $this->assertSame(
            ['OWNER', 'PRINCIPAL', 'COORDINATOR'],
            $controller->getConstant('MANAGERS')
        );

        $this->assertSame(
            ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER'],
            $controller->getConstant('MARKS_ROLES')
        );

        $this->assertSame(
            ['OWNER', 'PRINCIPAL'],
            $controller->getConstant('PUBLISH_ROLES')
        );
    }
}
