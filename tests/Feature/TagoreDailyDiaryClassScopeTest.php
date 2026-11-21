<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreDailyDiaryClassScopeTest extends TestCase
{
    private function academicFixture(): array
    {
        $student = User::query()->where('email', 'student@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $schoolId = (int) $student->school_id;
        $now = now();
        $yearId = DB::table('academic_years')->insertGetId([
            'school_id' => $schoolId, 'name' => 'Diary Scope '.uniqid(),
            'start_date' => now()->startOfYear(), 'end_date' => now()->endOfYear(),
            'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $standardId = DB::table('standards')->insertGetId([
            'school_id' => $schoolId, 'name' => 'Diary Scope Grade',
            'slug' => 'diary-scope-'.uniqid(), 'status' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $sectionId = DB::table('sections')->insertGetId([
            'school_id' => $schoolId, 'name' => 'Diary Scope Section',
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
        return [$student, $schoolId, $yearId, $standardLinkId, $standardId, $sectionId, $now];
    }

    public function test_teacher_diary_access_is_limited_to_currently_assigned_class(): void
    {
        $teacher = User::query()->where('email', 'teacher@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        [$student, $schoolId, $yearId, $standardLinkId, $standardId, $sectionId, $now] = $this->academicFixture();

        $this->actingAs($teacher)->get(route('tagore.diary.index', ['student_id' => $student->id]))->assertOk();
        $this->actingAs($teacher)->post(route('tagore.diary.save', $student->id), [
            'diary_date' => now()->toDateString(), 'homework' => 'Should be blocked',
        ])->assertForbidden();

        $subjectId = DB::table('subjects')->insertGetId([
            'school_id' => $schoolId, 'academic_year_id' => $yearId,
            'standard_id' => $standardId, 'section_id' => $sectionId,
            'name' => 'Diary Scope Subject', 'type' => 'core', 'status' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('class_teacher_links')->insert([
            'school_id' => $schoolId, 'academic_year_id' => $yearId,
            'standardLink_id' => $standardLinkId, 'subject_id' => $subjectId,
            'teacher_id' => $teacher->id, 'status' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->actingAs($teacher)->post(route('tagore.diary.save', $student->id), [
            'diary_date' => now()->toDateString(), 'homework' => 'Assigned class homework',
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_daily_diary', [
            'student_id' => $student->id, 'homework' => 'Assigned class homework',
            'school_updated_by' => $teacher->id,
        ]);
    }

    public function test_coordinator_diary_access_requires_exact_class_scope(): void
    {
        $coordinator = User::query()->where('email', 'coordinator@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        [$student, , , $standardLinkId] = $this->academicFixture();

        $this->actingAs($coordinator)->post(route('tagore.diary.save', $student->id), [
            'diary_date' => now()->toDateString(), 'homework' => 'Should be blocked',
        ])->assertForbidden();

        $permissionId = DB::table('tagore_permissions')->where('code', 'fee.view')->value('id');
        $this->assertNotNull($permissionId);
        DB::table('tagore_user_scopes')->insert([
            'user_id' => $coordinator->id, 'scope_type' => 'standard_link',
            'scope_id' => $standardLinkId, 'permission_id' => $permissionId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($coordinator)->post(route('tagore.diary.save', $student->id), [
            'diary_date' => now()->toDateString(), 'homework' => 'Scoped coordinator homework',
        ])->assertRedirect();
        $this->assertDatabaseHas('tagore_daily_diary', [
            'student_id' => $student->id, 'homework' => 'Scoped coordinator homework',
            'school_updated_by' => $coordinator->id,
        ]);
    }

    public function test_parent_diary_access_remains_limited_to_linked_children(): void
    {
        $parent = User::query()->where('email', 'tagore-demo-parent@example.com')->whereNull('deleted_at')->firstOrFail();
        [$student] = $this->academicFixture();
        DB::table('tagore_parent_students')->updateOrInsert(
            ['parent_user_id' => $parent->id, 'student_id' => $student->id],
            ['relationship' => 'Guardian', 'is_primary' => true, 'is_guardian' => true,
             'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
        );

        $this->actingAs($parent)->post(route('tagore.diary.save', $student->id), [
            'diary_date' => now()->toDateString(), 'study_hours' => 2,
            'parent_feedback' => 'Linked child feedback',
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_daily_diary', [
            'student_id' => $student->id, 'parent_updated_by' => $parent->id,
            'parent_feedback' => 'Linked child feedback',
        ]);
    }
}
