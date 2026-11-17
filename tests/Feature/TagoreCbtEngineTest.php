<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Tagore\CbtEngineService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreCbtEngineTest extends TestCase
{
    private User $owner;
    private User $student;
    private int $institutionId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $this->student = User::query()->where('email', 'student@tagore-demo.local')->firstOrFail();
        $this->institutionId = (int) DB::table('tagore_institutions')
            ->where('school_id', $this->student->school_id)->where('status', 'active')->value('id');
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) DB::rollBack();
        parent::tearDown();
    }

    public function test_jee_mock_test_runs_a_timed_attempt_with_negative_marking_and_percentile(): void
    {
        $service = app(CbtEngineService::class);
        $q1 = $service->createQuestion($this->institutionId, (int) $this->owner->id, [
            'exam_track' => 'jee_main', 'subject' => 'physics', 'difficulty' => 'medium',
            'question_type' => 'single_choice', 'question_text' => 'Which option is correct?',
            'options' => ['A' => 'Correct', 'B' => 'Incorrect', 'C' => 'Incorrect 2', 'D' => 'Incorrect 3'],
            'correct_answer' => 'A', 'positive_marks' => 4, 'negative_marks' => 1,
        ]);
        $q2 = $service->createQuestion($this->institutionId, (int) $this->owner->id, [
            'exam_track' => 'jee_main', 'subject' => 'physics', 'difficulty' => 'hard',
            'question_type' => 'single_choice', 'question_text' => 'Select the second answer.',
            'options' => ['A' => 'Option A', 'B' => 'Correct', 'C' => 'Option C', 'D' => 'Option D'],
            'correct_answer' => 'B', 'positive_marks' => 4, 'negative_marks' => 1,
        ]);
        $q3 = $service->createQuestion($this->institutionId, (int) $this->owner->id, [
            'exam_track' => 'jee_main', 'subject' => 'physics', 'difficulty' => 'medium',
            'question_type' => 'numeric', 'question_text' => 'Enter the numeric answer.',
            'correct_answer' => ['value' => 5, 'tolerance' => 0.1],
            'positive_marks' => 4, 'negative_marks' => 0,
        ]);

        foreach ([$q1, $q2, $q3] as $questionId) {
            $service->approveQuestion($questionId, $this->institutionId, (int) $this->owner->id);
        }

        $testId = $service->createMockTest($this->institutionId, (int) $this->owner->id, [
            'code' => 'QA-JEE-MAIN-001', 'title' => 'JEE Main acceptance mock',
            'exam_track' => 'jee_main', 'mode' => 'full_mock', 'duration_minutes' => 180,
            'attempt_limit' => 1,
        ]);
        $sectionId = $service->addSection($testId, 'Physics', 'physics', 1);
        $service->attachQuestions($sectionId, $this->institutionId, [
            ['question_id' => $q1], ['question_id' => $q2], ['question_id' => $q3],
        ]);
        $service->publishMockTest($testId, $this->institutionId);

        $attemptId = $service->startAttempt((int) $this->student->id, $testId, $this->institutionId);
        $questions = $service->questionsForAttempt((int) $this->student->id, $attemptId);
        $this->assertCount(3, $questions);

        $service->saveAnswer((int) $this->student->id, $attemptId, $q1, 'A');
        $service->saveAnswer((int) $this->student->id, $attemptId, $q2, 'C');
        $service->saveAnswer((int) $this->student->id, $attemptId, $q3, 5.05);

        $result = $service->submitAttempt((int) $this->student->id, $attemptId);
        $this->assertSame('submitted', $result->status);
        $this->assertSame('7.00', number_format((float) $result->score, 2, '.', ''));
        $this->assertSame(2, (int) $result->correct_count);
        $this->assertSame(1, (int) $result->incorrect_count);
        $this->assertSame(0, (int) $result->unattempted_count);
        $this->assertSame(1, (int) $result->rank);
        $this->assertSame('50.000', number_format((float) $result->percentile, 3, '.', ''));

        $again = $service->submitAttempt((int) $this->student->id, $attemptId);
        $this->assertSame($attemptId, (int) $again->id);
        $this->assertSame(1, DB::table('tagore_cbt_attempts')->where('id', $attemptId)->count());
        $this->assertDatabaseHas('tagore_cbt_attempt_events', ['attempt_id' => $attemptId, 'event_type' => 'submitted']);
    }

    public function test_attempt_is_idempotent_while_active_and_attempt_limit_applies_after_submission(): void
    {
        $service = app(CbtEngineService::class);
        $questionId = $service->createQuestion($this->institutionId, (int) $this->owner->id, [
            'exam_track' => 'neet', 'subject' => 'biology', 'difficulty' => 'easy',
            'question_type' => 'single_choice', 'question_text' => 'Biology test question.',
            'options' => ['A' => 'Correct', 'B' => 'Incorrect'],
            'correct_answer' => 'A', 'positive_marks' => 4, 'negative_marks' => 1,
        ]);
        $service->approveQuestion($questionId, $this->institutionId, (int) $this->owner->id);
        $testId = $service->createMockTest($this->institutionId, (int) $this->owner->id, [
            'code' => 'QA-NEET-MOCK-001', 'title' => 'NEET acceptance mock',
            'exam_track' => 'neet', 'duration_minutes' => 180, 'attempt_limit' => 1,
        ]);
        $sectionId = $service->addSection($testId, 'Biology', 'biology', 1);
        $service->attachQuestions($sectionId, $this->institutionId, [['question_id' => $questionId]]);
        $service->publishMockTest($testId, $this->institutionId);

        $first = $service->startAttempt((int) $this->student->id, $testId, $this->institutionId);
        $same = $service->startAttempt((int) $this->student->id, $testId, $this->institutionId);
        $this->assertSame($first, $same);
        $service->submitAttempt((int) $this->student->id, $first);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->startAttempt((int) $this->student->id, $testId, $this->institutionId);
    }

    public function test_question_bank_rejects_cross_track_or_subject_questions_when_building_a_mock(): void
    {
        $service = app(CbtEngineService::class);
        $questionId = $service->createQuestion($this->institutionId, (int) $this->owner->id, [
            'exam_track' => 'neet', 'subject' => 'biology', 'difficulty' => 'easy',
            'question_type' => 'single_choice', 'question_text' => 'NEET biology only.',
            'options' => ['A' => 'A', 'B' => 'B'], 'correct_answer' => 'A',
            'positive_marks' => 4, 'negative_marks' => 1,
        ]);
        $service->approveQuestion($questionId, $this->institutionId, (int) $this->owner->id);
        $testId = $service->createMockTest($this->institutionId, (int) $this->owner->id, [
            'code' => 'QA-JEE-TRACK-LOCK', 'title' => 'JEE track isolation',
            'exam_track' => 'jee_main', 'duration_minutes' => 180,
        ]);
        $sectionId = $service->addSection($testId, 'Physics', 'physics', 1);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->attachQuestions($sectionId, $this->institutionId, [['question_id' => $questionId]]);
    }
}
