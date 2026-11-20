<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TagoreStudentLifecycleTest extends TestCase
{
    private function promotionFixture(): array
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $schoolId = (int) $owner->school_id;
        $institution = DB::table('tagore_institutions')->where('school_id', $schoolId)->first();
        $this->assertNotNull($institution);

        $ownerRoleId = (int) DB::table('tagore_roles')->where('code', 'OWNER')->value('id');
        $this->assertTrue(
            DB::table('tagore_user_roles')
                ->where('user_id', $owner->id)
                ->where('role_id', $ownerRoleId)
                ->whereNull('institution_id')
                ->where('status', 'active')
                ->exists(),
            'The seeded Owner must have a global, active role assignment.'
        );

        $years = DB::table('academic_years')->where('school_id', $schoolId)->orderBy('id')->limit(2)->get();
        $this->assertCount(2, $years, 'The acceptance seed must provide two academic years.');

        $standardId = (int) DB::table('standards')->where('school_id', $schoolId)->orderBy('id')->value('id');
        $sectionId = (int) DB::table('sections')->where('school_id', $schoolId)->orderBy('id')->value('id');
        $teacherId = (int) User::query()->where('email', 'teacher@tagore-demo.local')->value('id');
        $this->assertGreaterThan(0, $standardId);
        $this->assertGreaterThan(0, $sectionId);
        $this->assertGreaterThan(0, $teacherId);

        $targetLinkId = DB::table('standards_link')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $years[1]->id,
            'class_teacher_id' => $teacherId,
            'standard_id' => $standardId,
            'section_id' => $sectionId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $student = User::factory()->create([
            'school_id' => $schoolId,
            'usergroup_id' => 6,
            'name' => 'Lifecycle Acceptance Student ' . Str::uuid(),
        ]);

        DB::table('student_academics')->insert([
            'school_id' => $schoolId,
            'academic_year_id' => $years[0]->id,
            'user_id' => $student->id,
            'standardLink_id' => null,
            'roll_number' => 'LIFE-' . $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$owner, $student, $institution, $years[0], $years[1], $targetLinkId];
    }

    public function test_owner_can_promote_student_and_retries_are_idempotent(): void
    {
        [$owner, $student, $institution, $sourceYear, $targetYear, $targetLinkId] = $this->promotionFixture();

        $payload = [
            'institution_id' => $institution->id,
            'source_academic_year_id' => $sourceYear->id,
            'target_academic_year_id' => $targetYear->id,
            'target_standard_link_id' => $targetLinkId,
            'student_ids' => [$student->id],
        ];

        $this->actingAs($owner)->post(route('tagore.students.promote'), $payload)->assertRedirect();

        $destination = DB::table('student_academics')
            ->where('school_id', $institution->school_id)
            ->where('academic_year_id', $targetYear->id)
            ->where('user_id', $student->id)
            ->whereNull('deleted_at')
            ->first();

        $this->assertNotNull($destination);
        $this->assertSame($targetLinkId, (int) $destination->standardLink_id);
        $this->assertDatabaseHas('tagore_student_lifecycle_events', [
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'event_type' => 'promotion',
            'source_academic_year_id' => $sourceYear->id,
            'target_academic_year_id' => $targetYear->id,
        ]);
        $this->assertDatabaseHas('student_academics', [
            'user_id' => $student->id,
            'academic_year_id' => $sourceYear->id,
            'roll_number' => 'LIFE-' . $student->id,
        ]);

        $this->actingAs($owner)->post(route('tagore.students.promote'), $payload)->assertRedirect();

        $this->assertSame(1, DB::table('student_academics')
            ->where('school_id', $institution->school_id)
            ->where('academic_year_id', $targetYear->id)
            ->where('user_id', $student->id)
            ->whereNull('deleted_at')
            ->count());
        $this->assertSame(1, DB::table('tagore_student_lifecycle_events')
            ->where('student_id', $student->id)
            ->where('event_type', 'promotion')
            ->count());
    }

    public function test_teacher_cannot_promote_students(): void
    {
        [$owner, $student, $institution, $sourceYear, $targetYear, $targetLinkId] = $this->promotionFixture();
        $teacher = User::query()->where('email', 'teacher@tagore-demo.local')->firstOrFail();

        $this->actingAs($teacher)->post(route('tagore.students.promote'), [
            'institution_id' => $institution->id,
            'source_academic_year_id' => $sourceYear->id,
            'target_academic_year_id' => $targetYear->id,
            'target_standard_link_id' => $targetLinkId,
            'student_ids' => [$student->id],
        ])->assertForbidden();

        $this->assertDatabaseMissing('student_academics', [
            'user_id' => $student->id,
            'academic_year_id' => $targetYear->id,
        ]);
        $this->assertDatabaseMissing('tagore_student_lifecycle_events', [
            'student_id' => $student->id,
            'event_type' => 'promotion',
        ]);
    }

    public function test_institution_principal_can_promote_students(): void
    {
        [$owner, $student, $institution, $sourceYear, $targetYear, $targetLinkId] = $this->promotionFixture();
        $principal = User::query()->where('email', 'principal@tagore-demo.local')->firstOrFail();

        $this->actingAs($principal)->post(route('tagore.students.promote'), [
            'institution_id' => $institution->id,
            'source_academic_year_id' => $sourceYear->id,
            'target_academic_year_id' => $targetYear->id,
            'target_standard_link_id' => $targetLinkId,
            'student_ids' => [$student->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_student_lifecycle_events', [
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'performed_by' => $principal->id,
            'event_type' => 'promotion',
        ]);
    }

    public function test_global_principal_assignment_does_not_grant_owner_authority(): void
    {
        // This test deliberately changes a shared demo identity. Keep every fixture
        // mutation inside a transaction so later browser acceptance sees the seeded
        // institution-scoped Principal assignment, not this global negative case.
        DB::beginTransaction();
        try {
        [$owner, $student, $institution, $sourceYear, $targetYear, $targetLinkId] = $this->promotionFixture();
        $principal = User::query()->where('email', 'principal@tagore-demo.local')->firstOrFail();
        $principalRoleId = (int) DB::table('tagore_roles')->where('code', 'PRINCIPAL')->value('id');

        // Build a deterministic identity: this account has exactly one role,
        // a global Principal assignment. It must not inherit Owner authority
        // or a second institution-scoped assignment from shared demo fixtures.
        DB::table('tagore_user_roles')->where('user_id', $principal->id)->delete();
        DB::table('tagore_user_roles')->insert([
            'user_id' => $principal->id,
            'role_id' => $principalRoleId,
            'institution_id' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(
            DB::table('tagore_user_roles as ur')
                ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
                ->where('ur.user_id', $principal->id)
                ->where('r.code', 'OWNER')
                ->where('ur.status', 'active')
                ->exists(),
            'The global Principal fixture must not carry Owner authority.'
        );

        // Other tests may reuse rolled-back auto-increment IDs; compare this
        // request's before/after counts instead of asserting global absence.
        $eventsBefore = DB::table('tagore_student_lifecycle_events')
            ->where('student_id', $student->id)
            ->where('event_type', 'promotion')
            ->count();
        $destinationRowsBefore = DB::table('student_academics')
            ->where('user_id', $student->id)
            ->where('academic_year_id', $targetYear->id)
            ->whereNull('deleted_at')
            ->count();

        $this->actingAs($principal)->post(route('tagore.students.promote'), [
            'institution_id' => $institution->id,
            'source_academic_year_id' => $sourceYear->id,
            'target_academic_year_id' => $targetYear->id,
            'target_standard_link_id' => $targetLinkId,
            'student_ids' => [$student->id],
        ])->assertForbidden();

        $this->assertSame($eventsBefore, DB::table('tagore_student_lifecycle_events')
            ->where('student_id', $student->id)
            ->where('event_type', 'promotion')
            ->count());
        $this->assertSame($destinationRowsBefore, DB::table('student_academics')
            ->where('user_id', $student->id)
            ->where('academic_year_id', $targetYear->id)
            ->whereNull('deleted_at')
            ->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_promotion_rejects_destination_class_link_from_another_year(): void
    {
        [$owner, $student, $institution, $sourceYear, $targetYear, $targetLinkId] = $this->promotionFixture();

        $wrongYearLinkId = DB::table('standards_link')->insertGetId([
            'school_id' => $institution->school_id,
            'academic_year_id' => $sourceYear->id,
            'class_teacher_id' => User::query()->where('email', 'teacher@tagore-demo.local')->value('id'),
            'standard_id' => DB::table('standards')->where('school_id', $institution->school_id)->value('id'),
            'section_id' => DB::table('sections')->where('school_id', $institution->school_id)->value('id'),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)->post(route('tagore.students.promote'), [
            'institution_id' => $institution->id,
            'source_academic_year_id' => $sourceYear->id,
            'target_academic_year_id' => $targetYear->id,
            'target_standard_link_id' => $wrongYearLinkId,
            'student_ids' => [$student->id],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('student_academics', [
            'user_id' => $student->id,
            'academic_year_id' => $targetYear->id,
        ]);
    }
}
