<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreOlympiadTest extends TestCase
{
    private function createDraft(int $institutionId, int $userId): int
    {
        return (int) DB::table('tagore_olympiads')->insertGetId([
            'institution_id' => $institutionId, 'title' => 'QA Science Olympiad', 'code' => 'QA-OLY-'.uniqid(),
            'description' => 'Synthetic QA event', 'target_classes' => json_encode(['6','7','8']),
            'duration_minutes' => 30, 'max_marks' => 1, 'exam_starts_at' => now()->addHour(), 'exam_ends_at' => now()->addHours(2), 'status' => 'draft', 'created_by' => $userId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_manager_can_create_an_olympiad_as_a_draft(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->value('id');
        $code = 'QA-'.strtoupper(substr(md5((string) microtime(true)), 0, 8));

        $this->actingAs($owner)->post(route('tagore.olympiad.store'), [
            'institution_id' => $institutionId, 'title' => 'Test Olympiad', 'code' => $code,
            'target_classes' => ['6','7'], 'duration_minutes' => 30, 'max_marks' => 20, 'exam_starts_at' => now()->addHour(), 'exam_ends_at' => now()->addHours(2),
        ])->assertRedirect(route('tagore.olympiad.index'));

        $this->assertDatabaseHas('tagore_olympiads', ['institution_id' => $institutionId, 'code' => $code, 'status' => 'draft']);
    }

    public function test_teacher_cannot_create_or_publish_olympiads(): void
    {
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $teacher->school_id)->value('id');

        $this->actingAs($teacher)->post(route('tagore.olympiad.store'), [
            'institution_id' => $institutionId, 'title' => 'Unauthorized', 'code' => 'NOPE',
            'target_classes' => ['6'], 'duration_minutes' => 30, 'max_marks' => 10,
        ])->assertForbidden();
    }

    public function test_question_import_requires_manager_approval_before_publish(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->value('id');
        $eventId = $this->createDraft($institutionId, (int) $owner->id);
        $payload = json_encode([[
            'question' => 'Which planet is known as the Red Planet?',
            'options' => ['A' => 'Venus', 'B' => 'Mars', 'C' => 'Jupiter', 'D' => 'Mercury'],
            'answer' => 'B', 'marks' => 1,
        ]]);

        $this->actingAs($owner)->post(route('tagore.olympiad.questions.import', $eventId), ['questions_json' => $payload])
            ->assertRedirect(route('tagore.olympiad.show', $eventId));
        $questionId = (int) DB::table('tagore_olympiad_questions')->where('olympiad_id', $eventId)->value('id');
        $this->assertDatabaseHas('tagore_olympiad_questions', ['id' => $questionId, 'approved_at' => null, 'correct_option' => 'B']);

        $this->from(route('tagore.olympiad.show', $eventId))->actingAs($owner)
            ->patch(route('tagore.olympiad.publish', $eventId))->assertSessionHasErrors('publish');

        $this->actingAs($owner)->patch(route('tagore.olympiad.questions.approve', $questionId))->assertRedirect();
        $this->actingAs($owner)->patch(route('tagore.olympiad.publish', $eventId))->assertRedirect(route('tagore.olympiad.index'));
        $this->assertDatabaseHas('tagore_olympiads', ['id' => $eventId, 'status' => 'published']);
    }
}