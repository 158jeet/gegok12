<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('e2e')]
class TagoreFamilyDiaryAcceptanceTest extends TestCase
{
    public function test_parent_can_read_and_write_diary_for_linked_child(): void
    {
        $parent = User::query()->where('usergroup_id', 7)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        $studentId = DB::table('tagore_parent_students')
            ->where('parent_user_id', $parent->id)->where('status', 'active')->value('student_id');

        $this->assertNotNull($studentId, 'Seed data must include a linked parent and student.');

        $this->actingAs($parent)->get(route('tagore.diary.index'))
            ->assertOk()->assertSee('Daily learning diary');

        $date = now()->toDateString();
        $this->actingAs($parent)->post(route('tagore.diary.save', ['studentId' => $studentId]), [
            'diary_date' => $date,
            'study_hours' => 2.5,
            'parent_feedback' => 'Acceptance test parent feedback',
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_daily_diary', [
            'student_id' => $studentId,
            'diary_date' => $date,
            'study_hours' => 2.5,
            'parent_feedback' => 'Acceptance test parent feedback',
            'parent_updated_by' => $parent->id,
        ]);
    }

    public function test_parent_cannot_read_or_write_diary_for_unlinked_child(): void
    {
        $parent = User::query()->where('usergroup_id', 7)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        $unlinkedStudentId = DB::table('users')
            ->where('usergroup_id', 6)->whereNull('deleted_at')
            ->whereNotExists(function ($query) use ($parent) {
                $query->select(DB::raw(1))->from('tagore_parent_students as ps')
                    ->whereColumn('ps.student_id', 'users.id')
                    ->where('ps.parent_user_id', $parent->id)->where('ps.status', 'active');
            })->value('id');

        if (!$unlinkedStudentId) {
            $this->markTestSkipped('Seed data has no unlinked student to test the denial path.');
        }

        $this->actingAs($parent)->post(route('tagore.diary.save', ['studentId' => $unlinkedStudentId]), [
            'diary_date' => now()->toDateString(),
            'study_hours' => 1,
            'parent_feedback' => 'Must not be stored',
        ])->assertForbidden();

        $this->assertDatabaseMissing('tagore_daily_diary', [
            'student_id' => $unlinkedStudentId,
            'parent_feedback' => 'Must not be stored',
        ]);
    }

    public function test_school_staff_can_write_school_fields_without_overwriting_parent_fields(): void
    {
        $parent = User::query()->where('usergroup_id', 7)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        $studentId = DB::table('tagore_parent_students')
            ->where('parent_user_id', $parent->id)->where('status', 'active')->value('student_id');
        $this->assertNotNull($studentId, 'Seed data must include a linked parent and student.');

        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        $date = now()->toDateString();

        DB::table('tagore_daily_diary')->updateOrInsert(
            ['student_id' => $studentId, 'diary_date' => $date],
            ['institution_id' => DB::table('tagore_institutions')->where('school_id', DB::table('users')->where('id', $studentId)->value('school_id'))->value('id'),
             'study_hours' => 2.5, 'parent_feedback' => 'Keep parent data', 'created_at' => now(), 'updated_at' => now()]
        );

        $this->actingAs($teacher)->post(route('tagore.diary.save', ['studentId' => $studentId]), [
            'diary_date' => $date,
            'homework' => 'Acceptance test homework',
            'school_feedback' => 'Acceptance test school feedback',
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_daily_diary', [
            'student_id' => $studentId,
            'diary_date' => $date,
            'study_hours' => 2.5,
            'parent_feedback' => 'Keep parent data',
            'homework' => 'Acceptance test homework',
            'school_feedback' => 'Acceptance test school feedback',
            'school_updated_by' => $teacher->id,
        ]);
    }
}
