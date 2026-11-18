<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreCbtManagementTest extends TestCase
{
    public function test_management_routes_are_registered(): void
    {
        foreach ([
            'tagore.cbt.manage',
            'tagore.cbt.banks.store',
            'tagore.cbt.questions.store',
            'tagore.cbt.questions.import',
            'tagore.cbt.questions.update',
            'tagore.cbt.questions.delete',
            'tagore.cbt.exams.store',
            'tagore.cbt.exams.questions.attach',
            'tagore.cbt.exams.publish',
            'tagore.cbt.exams.schedule',
            'tagore.cbt.exams.analytics',
            'tagore.cbt.exams.analytics.export',
            'tagore.cbt.students.progress',
        ] as $name) {
            $this->assertNotNull(app('router')->getRoutes()->getByName($name), "Missing CBT management route {$name}");
        }
    }

    public function test_owner_can_open_management_workspace_but_student_cannot(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $student = User::query()->where('email', 'student@tagore-demo.local')->firstOrFail();

        $this->actingAs($owner)->get(route('tagore.cbt.manage'))->assertOk()->assertSee('JEE / NEET CBT Management');
        $this->actingAs($student)->get(route('tagore.cbt.manage'))->assertForbidden();
    }

    public function test_owner_can_create_bank_import_questions_and_create_draft_exam(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');
        $this->assertGreaterThan(0, $institutionId);

        $bankResponse = $this->actingAs($owner)->postJson(route('tagore.cbt.banks.store'), [
            'institution_id' => $institutionId,
            'name' => 'Acceptance CBT Bank',
            'exam_track' => 'JEE_MAIN',
        ])->assertCreated()->assertJsonPath('created', true);

        $bankId = (int) $bankResponse->json('bank_id');
        $this->assertGreaterThan(0, $bankId);

        $question = [
            'subject_code' => 'physics',
            'chapter' => 'Kinematics',
            'difficulty' => 'medium',
            'question_type' => 'single_choice',
            'stem' => 'A body starts from rest. Which quantity is zero initially?',
            'options' => ['A' => 'Velocity', 'B' => 'Acceleration'],
            'answer_key' => 'A',
            'explanation' => 'The body starts from rest.',
            'default_marks' => 4,
            'default_negative_marks' => 1,
        ];

        $this->postJson(route('tagore.cbt.questions.import', $bankId), [
            'questions' => [$question],
        ])->assertCreated()->assertJsonPath('imported', 1);

        $this->assertDatabaseHas('tagore_cbt_questions', [
            'question_bank_id' => $bankId,
            'subject_code' => 'physics',
            'status' => 'approved',
        ]);

        $examResponse = $this->postJson(route('tagore.cbt.exams.store'), [
            'institution_id' => $institutionId,
            'title' => 'Acceptance JEE Main Mock',
            'exam_track' => 'JEE_MAIN',
            'duration_minutes' => 180,
        ])->assertCreated()->assertJsonPath('created', true);

        $this->assertDatabaseHas('tagore_cbt_exams', [
            'id' => (int) $examResponse->json('exam_id'),
            'status' => 'draft',
            'exam_track' => 'JEE_MAIN',
        ]);
    }

    public function test_bulk_question_import_rolls_back_when_any_question_is_invalid(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');

        $bank = $this->actingAs($owner)->postJson(route('tagore.cbt.banks.store'), [
            'institution_id' => $institutionId,
            'name' => 'Atomic Import Test',
            'exam_track' => 'NEET',
        ])->assertCreated()->json('bank_id');

        $valid = [
            'subject_code' => 'biology',
            'difficulty' => 'easy',
            'question_type' => 'single_choice',
            'stem' => 'Which is a cell organelle?',
            'options' => ['A' => 'Mitochondrion', 'B' => 'Cell wall'],
            'answer_key' => 'A',
            'default_marks' => 4,
            'default_negative_marks' => 1,
        ];

        $this->postJson(route('tagore.cbt.questions.import', $bank), [
            'questions' => [$valid, ['stem' => 'Invalid second question']],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('tagore_cbt_questions', [
            'question_bank_id' => $bank,
            'stem' => 'Which is a cell organelle?',
        ]);
    }

    public function test_student_progress_is_scoped_and_returns_trends_and_topic_recommendations(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $student = User::query()->where('email', 'student@tagore-demo.local')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');
        $now = now();

        $bankId = DB::table('tagore_cbt_question_banks')->insertGetId([
            'institution_id' => $institutionId, 'name' => 'Progress test bank', 'exam_track' => 'JEE_MAIN',
            'created_by' => $owner->id, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $questionId = DB::table('tagore_cbt_questions')->insertGetId([
            'question_bank_id' => $bankId, 'subject_code' => 'physics', 'chapter' => 'Kinematics',
            'difficulty' => 'medium', 'question_type' => 'single_choice', 'stem' => 'Progress test question',
            'options_json' => json_encode(['A' => 'A', 'B' => 'B']), 'answer_key_json' => json_encode('A'),
            'default_marks' => 4, 'default_negative_marks' => 1, 'status' => 'approved',
            'created_by' => $owner->id, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $examId = DB::table('tagore_cbt_exams')->insertGetId([
            'institution_id' => $institutionId, 'title' => 'Progress test mock', 'exam_track' => 'JEE_MAIN',
            'duration_minutes' => 60, 'status' => 'published', 'created_by' => $owner->id,
            'published_by' => $owner->id, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $examQuestionId = DB::table('tagore_cbt_exam_questions')->insertGetId([
            'exam_id' => $examId, 'question_id' => $questionId, 'display_order' => 1,
            'marks' => 4, 'negative_marks' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $attemptIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $attemptIds[] = DB::table('tagore_cbt_attempts')->insertGetId([
                'exam_id' => $examId, 'student_id' => $student->id, 'attempt_no' => $i,
                'status' => 'submitted', 'started_at' => $now->copy()->subDays(4 - $i),
                'expires_at' => $now, 'submitted_at' => $now->copy()->subDays(4 - $i),
                'score' => 0, 'max_score' => 4, 'accuracy' => 0,
                'correct_count' => 0, 'wrong_count' => 1, 'unanswered_count' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        foreach ($attemptIds as $attemptId) {
            DB::table('tagore_cbt_responses')->insert([
                'attempt_id' => $attemptId, 'exam_question_id' => $examQuestionId,
                'answer_json' => json_encode('B'), 'marked_for_review' => false, 'time_spent_seconds' => 30,
                'is_correct' => false, 'awarded_marks' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $this->actingAs($owner)->getJson(route('tagore.cbt.students.progress', $student->id))
            ->assertOk()->assertJsonPath('attempt_count', 3)
            ->assertJsonPath('progress_trend.0.exam_title', 'Progress test mock')
            ->assertJsonPath('weak_topics.0.chapter', 'Kinematics')
            ->assertJsonPath('weak_topics.0.recommendation', 'Priority revision: revisit concepts, then solve guided questions');

        $this->actingAs($student)->getJson(route('tagore.cbt.students.progress', $student->id))->assertForbidden();
    }

}
