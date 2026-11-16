<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('e2e')]
class TagoreFeeScopeAuthorizationTest extends TestCase
{
    private function academicFixture(): array
    {
        $student = User::query()->where('email', 'student@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $schoolId = (int) $student->school_id;
        $now = now();

        $yearId = DB::table('academic_years')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Fee Scope Test '.uniqid(),
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $standardId = DB::table('standards')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Fee Scope Test Grade',
            'slug' => 'fee-scope-'.uniqid(),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sectionId = DB::table('sections')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Fee Scope Test Section',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $standardLinkId = DB::table('standards_link')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'class_teacher_id' => $student->id,
            'standard_id' => $standardId,
            'section_id' => $sectionId,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('student_academics')->insert([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'user_id' => $student->id,
            'standardLink_id' => $standardLinkId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$student, $schoolId, $yearId, $standardLinkId, $standardId, $sectionId, $now];
    }

    public function test_coordinator_can_only_view_fee_status_for_explicitly_scoped_class(): void
    {
        $coordinator = User::query()->where('email', 'coordinator@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        [$student, , , $standardLinkId] = $this->academicFixture();
        $this->actingAs($coordinator)->get(route('tagore.fees.student', $student->id))->assertForbidden();

        $permissionId = DB::table('tagore_permissions')->where('code', 'fee.view')->value('id');
        $this->assertNotNull($permissionId);
        DB::table('tagore_user_scopes')->insert([
            'user_id' => $coordinator->id,
            'scope_type' => 'standard_link',
            'scope_id' => $standardLinkId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($coordinator)->get(route('tagore.fees.student', $student->id))->assertOk();
    }

    public function test_teacher_can_only_view_fee_status_for_an_assigned_class(): void
    {
        $teacher = User::query()->where('email', 'teacher@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        [$student, $schoolId, $yearId, $standardLinkId, $standardId, $sectionId, $now] = $this->academicFixture();
        $this->actingAs($teacher)->get(route('tagore.fees.student', $student->id))->assertForbidden();

        $subjectId = DB::table('subjects')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'standard_id' => $standardId,
            'section_id' => $sectionId,
            'name' => 'Fee Scope Test Subject',
            'type' => 'core',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('class_teacher_links')->insert([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'standardLink_id' => $standardLinkId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacher->id,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($teacher)->get(route('tagore.fees.student', $student->id))->assertOk();
    }
}
