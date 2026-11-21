<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreFeeClassScopeTest extends TestCase
{
    private function addAcademicClass(User $student, string $label): array
    {
        $schoolId = (int) $student->school_id;
        $now = now();
        $yearId = DB::table('academic_years')->insertGetId([
            'school_id' => $schoolId, 'name' => 'Fee Scope ' . $label . ' ' . bin2hex(random_bytes(3)),
            'start_date' => now()->startOfYear(), 'end_date' => now()->endOfYear(),
            'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $standardId = DB::table('standards')->insertGetId([
            'school_id' => $schoolId, 'name' => 'Fee Scope Grade ' . $label,
            'slug' => 'fee-scope-' . strtolower($label) . '-' . bin2hex(random_bytes(3)),
            'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sectionId = DB::table('sections')->insertGetId([
            'school_id' => $schoolId, 'name' => 'Fee Scope Section ' . $label,
            'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $standardLinkId = DB::table('standards_link')->insertGetId([
            'school_id' => $schoolId, 'academic_year_id' => $yearId,
            'class_teacher_id' => $student->id, 'standard_id' => $standardId,
            'section_id' => $sectionId, 'status' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('student_academics')->insert([
            'school_id' => $schoolId, 'academic_year_id' => $yearId,
            'user_id' => $student->id, 'standardLink_id' => $standardLinkId,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return [$schoolId, $yearId, $standardLinkId, $standardId, $sectionId, $now];
    }

    private function assignTeacher(User $teacher, array $class): void
    {
        [$schoolId, $yearId, $standardLinkId, $standardId, $sectionId, $now] = $class;
        $subjectId = DB::table('subjects')->insertGetId([
            'school_id' => $schoolId, 'academic_year_id' => $yearId,
            'standard_id' => $standardId, 'section_id' => $sectionId,
            'name' => 'Fee Scope Subject ' . bin2hex(random_bytes(3)),
            'type' => 'core', 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('class_teacher_links')->insert([
            'school_id' => $schoolId, 'academic_year_id' => $yearId,
            'standardLink_id' => $standardLinkId, 'subject_id' => $subjectId,
            'teacher_id' => $teacher->id, 'status' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function test_teacher_fee_statement_is_limited_to_the_currently_assigned_class(): void
    {
        $teacher = User::query()->where('email', 'teacher@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $assignedStudent = User::query()->where('email', 'student@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $otherStudent = User::factory()->create([
            'school_id' => $assignedStudent->school_id,
            'usergroup_id' => 6,
            'name' => 'Unassigned Fee Scope Student',
            'email' => 'fee-unassigned-' . bin2hex(random_bytes(4)) . '@tagore-demo.local',
        ]);

        $assignedClass = $this->addAcademicClass($assignedStudent, 'Teacher');
        $this->addAcademicClass($otherStudent, 'Other');
        $this->assignTeacher($teacher, $assignedClass);

        $this->actingAs($teacher)
            ->get(route('tagore.fees.student', $assignedStudent->id))
            ->assertOk();
        $this->actingAs($teacher)
            ->get(route('tagore.fees.student', $otherStudent->id))
            ->assertForbidden();
        $this->actingAs($teacher)
            ->get(route('tagore.fees.accounts'))
            ->assertOk()
            ->assertDontSee('Online settlement reconciliation')
            ->assertDontSee('Reconcile settlement');
    }

    public function test_coordinator_fee_statement_requires_exact_standard_link_scope(): void
    {
        $coordinator = User::query()->where('email', 'coordinator@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $assignedStudent = User::query()->where('email', 'student@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $otherStudent = User::factory()->create([
            'school_id' => $assignedStudent->school_id,
            'usergroup_id' => 6,
            'name' => 'Unassigned Coordinator Fee Student',
            'email' => 'fee-coordinator-unassigned-' . bin2hex(random_bytes(4)) . '@tagore-demo.local',
        ]);

        [, , $standardLinkId] = $this->addAcademicClass($assignedStudent, 'Coordinator');
        $this->addAcademicClass($otherStudent, 'OtherCoordinator');
        $permissionId = DB::table('tagore_permissions')->where('code', 'fee.view')->value('id');
        $this->assertNotNull($permissionId);

        $this->actingAs($coordinator)
            ->get(route('tagore.fees.student', $assignedStudent->id))
            ->assertForbidden();

        DB::table('tagore_user_scopes')->insert([
            'user_id' => $coordinator->id,
            'scope_type' => 'standard_link',
            'scope_id' => $standardLinkId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($coordinator)
            ->get(route('tagore.fees.student', $assignedStudent->id))
            ->assertOk();
        $this->actingAs($coordinator)
            ->get(route('tagore.fees.student', $otherStudent->id))
            ->assertForbidden();
        $this->actingAs($coordinator)
            ->get(route('tagore.fees.accounts'))
            ->assertOk()
            ->assertDontSee('Online settlement reconciliation')
            ->assertDontSee('Reconcile settlement');
    }

    public function test_accounts_workspace_applies_class_scoped_student_ids_to_all_financial_aggregates(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Tagore/FeeController.php'));

        $this->assertStringContainsString('$summaryQuery->whereIn(\'student_id\', $scopedStudentIds)', $source);
        $this->assertStringContainsString('$recentQuery->whereIn(\'p.student_id\', $scopedStudentIds)', $source);
        $this->assertStringContainsString('$familyQuery->whereIn(\'ps.student_id\', $scopedStudentIds)', $source);
        $this->assertStringContainsString('$pendingReconciliation = $scopedStudentIds === null', $source);
    }
}
