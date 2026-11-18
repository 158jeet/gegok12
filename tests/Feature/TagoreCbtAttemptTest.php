<?php

namespace Tests\\Feature;

use App\\Models\\User;
use Illuminate\\Support\\Facades\\DB;
use Tests\\TestCase;

class TagoreCbtAttemptTest extends TestCase
{
    private function publishedExam(): array
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $student = User::query()->where('email', 'student@tagore-demo.local')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');
        $now = now();

        $bankId = DB::table('tagore_cbt_question_banks')->insertGetId([
            'institution_id' => $institutionId, 'name' => 'Attempt lifecycle test bank',
            'exam_track' => 'JEE_MAIN', 'created_by' => $owner->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $questionId = DB::table('tagore_cbt_questions')->insertGetId([
            'question_bank_id' => $bankId, 'subject_code' => 'physics', 'chapter' => 'Kinematics',
            'difficulty' => 'easy', 'question_type' => 'single_choice',
            'stem' => 'Which option is correct?', 'options_json' => json_encode(['A' => 'One', 'B' => 'Two']),
            'answer_key_json' => json_encode('B'), 'default_marks' => 4, 'default_negative_marks' => 1,
            'status' => 'approved', 'created_by' => $owner->id, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $examId = DB::table('tagore_cbt_exams')->insertGetId([
            'institution_id' => $institutionId, 'title' => 'Attempt lifecycle mock',
            'exam_track' => 'JEE_MAIN', 'duration_minutes' => 60, 'status' => 'published',
            'created_by' => $owner->id, 'published_by' => $owner->id, 'published_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $examQuestionId = DB::table('tagore_cbt_exam_questions')->insertGetId([
            'exam_id' => $examId, 'question_id' => $questionId, 'display_order' => 1,
            'marks' => 4, 'negative_marks' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return compact('owner', 'student', 'examId', 'examQuestionId');
    }

    public function test_student_attempt_validates_answers_hides_answer_key_and_preserves_response_creation_time(): void
    {
        $fixture = $this->publishedExam();
        $student = $fixture['student'];
        $examId = $fixture['examId'];
        $questionId = $fixture['examQuestionId'];

        $start = $this->actingAs($student)->postJson(route('tagore.cbt.attempts.start', $examId))
            ->assertOk()->assertJsonPath('status', 'in_progress');
        $attemptId = (int) $start->json('attempt_id');

        $this->getJson(route('tagore.cbt.attempts.player', $attemptId))
            ->assertOk()->assertJsonMissingPath('questions.0.answer_key_json')
            ->assertJsonPath('questions.0.options.A', 'One');

        $this->putJson(route('tagore.cbt.attempts.response', $attemptId), [
            'exam_question_id' => $questionId, 'answer' => ['B'],
        ])->assertUnprocessable();

        $this->putJson(route('tagore.cbt.attempts.response', $attemptId), [
            'exam_question_id' => $questionId, 'answer' => 'B', 'time_spent_seconds' => 15,
        ])->assertOk()->assertJsonPath('saved', true);
        $createdAt = DB::table('tagore_cbt_responses')->where('attempt_id', $attemptId)->value('created_at');

        $this->putJson(route('tagore.cbt.attempts.response', $attemptId), [
            'exam_question_id' => $questionId, 'answer' => 'A', 'time_spent_seconds' => 30,
        ])->assertOk();
        $this->assertSame($createdAt, DB::table('tagore_cbt_responses')->where('attempt_id', $attemptId)->value('created_at'));

        $this->postJson(route('tagore.cbt.attempts.submit', $attemptId))
            ->assertOk()->assertJsonPath('submitted', true)->assertJsonPath('result.score', -1)
            ->assertJsonPath('result.rank', 1)->assertJsonPath('result.candidates', 1)
            ->assertJsonPath('result.percentile', 100);
        $this->getJson(route('tagore.cbt.attempts.result', $attemptId))
            ->assertOk()->assertJsonPath('result.score', -1);
        $this->postJson(route('tagore.cbt.attempts.submit', $attemptId))
            ->assertOk()->assertJsonPath('result.score', -1);
    }

    public function test_attempt_routes_reject_owner_and_cannot_be_read_by_another_student(): void
    {
        $fixture = $this->publishedExam();
        $start = $this->actingAs($fixture['student'])
            ->postJson(route('tagore.cbt.attempts.start', $fixture['examId']))->assertOk();
        $attemptId = (int) $start->json('attempt_id');

        $this->actingAs($fixture['owner'])
            ->getJson(route('tagore.cbt.attempts.player', $attemptId))->assertForbidden();
    }
}
