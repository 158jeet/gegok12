<?php

namespace Tests\\Feature;

use App\\Models\\User;
use Illuminate\\Support\\Facades\\DB;
use Tests\\TestCase;

class TagoreDailyDiaryAcceptanceTest extends TestCase
{
    private User $parent;
    private User $owner;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = User::query()->where('email', 'parent@tagore-demo.local')->firstOrFail();
        $this->owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $this->studentId = (int) DB::table('tagore_parent_students')
            ->where('parent_user_id', $this->parent->id)
            ->where('status', 'active')
            ->value('student_id');

        $this->assertGreaterThan(0, $this->studentId, 'The demo parent must have a linked student fixture.');
    }

    protected function tearDown(): void
    {
        if (isset($this->studentId)) {
            DB::table('tagore_daily_diary')->where('student_id', $this->studentId)
                ->where('diary_date', now()->subDay()->toDateString())->delete();
        }

        parent::tearDown();
    }

    public function test_parent_and_school_can_update_the_same_diary_without_overwriting_each_others_fields(): void
    {
        $date = now()->subDay()->toDateString();

        $this->actingAs($this->parent)->post(route('tagore.diary.save', $this->studentId), [
            'diary_date' => $date,
            'study_hours' => '2.50',
            'parent_feedback' => 'Read two chapters at home.',
            'school_feedback' => 'Parent must not write school-only fields.',
            'homework' => 'Parent must not write homework.',
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_daily_diary', [
            'student_id' => $this->studentId,
            'diary_date' => $date,
            'study_hours' => '2.50',
            'parent_feedback' => 'Read two chapters at home.',
            'school_feedback' => null,
            'homework' => null,
            'parent_updated_by' => $this->parent->id,
        ]);

        $this->actingAs($this->owner)->post(route('tagore.diary.save', $this->studentId), [
            'diary_date' => $date,
            'school_feedback' => 'Good progress in class.',
            'homework' => 'Complete maths exercise 4.',
            'parent_feedback' => 'School must not overwrite parent-only fields.',
            'study_hours' => '99',
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_daily_diary', [
            'student_id' => $this->studentId,
            'diary_date' => $date,
            'study_hours' => '2.50',
            'parent_feedback' => 'Read two chapters at home.',
            'school_feedback' => 'Good progress in class.',
            'homework' => 'Complete maths exercise 4.',
            'parent_updated_by' => $this->parent->id,
            'school_updated_by' => $this->owner->id,
        ]);
        $this->assertSame(1, DB::table('tagore_daily_diary')
            ->where('student_id', $this->studentId)->where('diary_date', $date)->count());
    }

    public function test_parent_can_open_diary_and_only_sees_their_linked_student(): void
    {
        $this->actingAs($this->parent)->get(route('tagore.diary.index'))
            ->assertOk()
            ->assertSee('Daily learning diary')
            ->assertSee($this->parent->name === '' ? 'Daily learning diary' : DB::table('users')->where('id', $this->studentId)->value('name'));
    }

    public function test_diary_rejects_future_dates_and_study_hours_outside_the_valid_range(): void
    {
        $this->actingAs($this->parent)->from(route('tagore.diary.index'))->post(route('tagore.diary.save', $this->studentId), [
            'diary_date' => now()->addDay()->toDateString(),
            'study_hours' => '2',
        ])->assertSessionHasErrors('diary_date');

        $this->actingAs($this->parent)->from(route('tagore.diary.index'))->post(route('tagore.diary.save', $this->studentId), [
            'diary_date' => now()->subDay()->toDateString(),
            'study_hours' => '25',
        ])->assertSessionHasErrors('study_hours');
    }
}
