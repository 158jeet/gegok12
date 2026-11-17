<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Tagore\CoachingCbtService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TagoreCoachingCbtTest extends TestCase
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
        $this->assertGreaterThan(0, $this->institutionId);
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function buildPublishedPaper(int $requiredAnswers = 2): int
    {
        $service = app(CoachingCbtService::class);
        $questionIds = [];
        foreach ([
            ['code' => 'QA-CBT-001', 'stem' => 'Which particle has a negative charge?', 'answer' => 'B'],
            ['code' => 'QA-CBT-002', 'stem' => 'Which quantity is measured in newtons?', 'answer' => 'A'],
        ] as $row) {
            $questionId = $service->createQuestion($this->institutionId, (int) $this->owner->id, [
                'question_code' => $row['code'],
                'subject' => 'PHYSICS',
                'chapter' => 'Units and particles',
                'topic' => 'Fundamentals',
                'difficulty' => 'easy',
                'question_type' => 'single_choice',
                'stem' => $row['stem'],
                'options' => ['A' => 'Mass', 'B' => 'Electron', 'C' => 'Energy', 'D' => 'Power'],
                'answer' => $row['answer'],
                'explanation' => 'Synthetic explanation for CBT acceptance.',
                'default_marks' => 4,
                'default_negative_marks' => 1,
            ]);
            $service->approveQuestion($questionId);
            $questionIds[] = $questionId;
        }

        $paperId = $service->createPaper($this->institutionId, (int) $this->owner->id, [
            'name' => 'QA JEE Main Physics Mock',
            'exam_type' => 'JEE_MAIN',
            'duration_minutes' => 90,
            'max_attempts' => 1,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'sections' => [
                ['section_key' => 'P', 'title' => 'Physics', 'question_limit' => 2, 'required_answer_count' => $requiredAnswers, 'section_type' => 'single_choice'],
            ],
        ]);
        $sectionId = (int) DB::table('tagore_cbt_sections')->where('paper_id', $paperId)->value('id');
        foreach ($questionIds as $questionId) {
            $service->addQuestionToPaper($paperId, $sectionId, $questionId, 4, 1);
        }
        $service->publishPaper($paperId, (int) $this->owner->id);
        return $paperId;
    }

    public function test_question_bank_blueprint_publish_and_student_attempt_score_are_separate_from_school_exams(): void
    {
        $paperId = $this->buildPublishedPaper();
        $this->assertDatabaseHas('tagore_cbt_papers', ['id' => $paperId, 'exam_type' => 'JEE_MAIN', 'status' => 'published']);
        $this->assertSame(2, DB::table('tagore_cbt_paper_questions')->where('paper_id', $paperId)->count());

        $service = app(CoachingCbtService::class);
        $attemptId = $service->startAttempt($paperId, (int) $this->student->id);
        $payload = $service->playerPayload($attemptId, (int) $this->student->id);
        $this->assertCount(2, $payload['questions']);
        $this->assertArrayNotHasKey('correct_answer', $payload['questions'][0]['question']);
        $this->assertArrayNotHasKey('explanation', $payload['questions'][0]['question']);

        $questionIds = json_decode($payload['attempt']->question_order_json, true);
        $service->saveAnswer($attemptId, (int) $this->student->id, (int) $questionIds[0], 'B', false, 25);
        $service->saveAnswer($attemptId, (int) $this->student->id, (int) $questionIds[1], 'D', true, 30);
        $result = $service->scoreAttempt($attemptId);

        $this->assertSame('submitted', $result->status);
        $this->assertSame('3.00', number_format((float) $result->score, 2, '.', ''));
        $this->assertSame(1, (int) $result->correct_count);
        $this->assertSame(1, (int) $result->incorrect_count);
        $this->assertSame(0, (int) $result->unanswered_count);
        $this->assertSame(1, (int) $result->rank);
        $this->assertSame('100.0000', number_format((float) $result->percentile, 4, '.', ''));
        $this->assertDatabaseHas('tagore_cbt_events', ['attempt_id' => $attemptId, 'event' => 'attempt_submitted']);
        $this->assertDatabaseHas('tagore_cbt_attempt_topic_stats', [
            'attempt_id' => $attemptId,
            'subject' => 'PHYSICS',
            'chapter' => 'Units and particles',
            'topic' => 'Fundamentals',
            'total_questions' => 2,
            'attempted_count' => 2,
            'scored_count' => 2,
            'correct_count' => 1,
            'incorrect_count' => 1,
            'unattempted_count' => 0,
            'score' => '3.00',
        ]);
    }

    public function test_optional_section_scores_only_the_required_number_of_attempted_questions(): void
    {
        $paperId = $this->buildPublishedPaper(1);
        $service = app(CoachingCbtService::class);
        $attemptId = $service->startAttempt($paperId, (int) $this->student->id);
        $questionIds = json_decode(DB::table('tagore_cbt_attempts')->where('id', $attemptId)->value('question_order_json'), true);

        $service->saveAnswer($attemptId, (int) $this->student->id, (int) $questionIds[0], 'B', false, 20);
        $result = $service->scoreAttempt($attemptId);

        $this->assertSame('4.00', number_format((float) $result->score, 2, '.', ''));
        $this->assertSame('4.00', number_format((float) $result->max_score, 2, '.', ''));
        $this->assertSame(1, (int) $result->correct_count);
        $this->assertSame(0, (int) $result->incorrect_count);
        $this->assertSame(0, (int) $result->unanswered_count);
    }

    public function test_student_cannot_reuse_an_attempt_or_save_answers_after_submission(): void
    {
        $paperId = $this->buildPublishedPaper();
        $service = app(CoachingCbtService::class);
        $attemptId = $service->startAttempt($paperId, (int) $this->student->id);
        $questionId = (int) DB::table('tagore_cbt_paper_questions')->where('paper_id', $paperId)->value('question_id');
        $service->scoreAttempt($attemptId);

        try {
            $service->saveAnswer($attemptId, (int) $this->student->id, $questionId, 'A', false, 10);
            $this->fail('A submitted attempt must not accept further answers.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        try {
            $service->startAttempt($paperId, (int) $this->student->id);
            $this->fail('A student must not exceed the configured attempt limit.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_parent_cannot_create_question_or_start_a_coaching_mock(): void
    {
        $parent = User::query()->where('email', 'parent@tagore-demo.local')->firstOrFail();
        $this->actingAs($parent)->post(route('tagore.cbt.questions.store'), [
            'institution_id' => $this->institutionId,
            'subject' => 'PHYSICS',
            'chapter' => 'Mechanics',
            'difficulty' => 'easy',
            'question_type' => 'single_choice',
            'stem' => 'Unauthorized question?',
            'options' => ['A' => 'A', 'B' => 'B'],
            'answer' => 'A',
        ])->assertForbidden();

        $paperId = $this->buildPublishedPaper();
        $this->actingAs($parent)->post(route('tagore.cbt.start', $paperId))->assertForbidden();
    }

    public function test_student_cannot_force_submit_another_students_attempt(): void
    {
        $paperId = $this->buildPublishedPaper();
        $service = app(CoachingCbtService::class);
        $attemptId = $service->startAttempt($paperId, (int) $this->student->id);
        $otherStudent = User::factory()->create([
            'name' => 'Other CBT Student',
            'email' => 'other-cbt-' . bin2hex(random_bytes(4)) . '@example.invalid',
            'school_id' => $this->student->school_id,
            'usergroup_id' => 6,
        ]);

        $this->actingAs($otherStudent)
            ->post(route('tagore.cbt.submit', $attemptId))
            ->assertNotFound();

        $this->assertDatabaseHas('tagore_cbt_attempts', [
            'id' => $attemptId,
            'student_id' => $this->student->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_expired_attempt_is_finalized_as_expired_and_cannot_be_resumed(): void
    {
        $paperId = $this->buildPublishedPaper();
        $service = app(CoachingCbtService::class);
        $attemptId = $service->startAttempt($paperId, (int) $this->student->id);
        DB::table('tagore_cbt_attempts')->where('id', $attemptId)->update([
            'expires_at' => now()->subSecond(),
            'updated_at' => now(),
        ]);

        $result = $service->scoreAttempt($attemptId);
        $this->assertSame('expired', $result->status);

        try {
            $service->saveAnswer($attemptId, (int) $this->student->id, (int) DB::table('tagore_cbt_paper_questions')->where('paper_id', $paperId)->value('question_id'), 'A', false, 10);
            $this->fail('An expired attempt must not accept answers.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_coaching_cbt_routes_are_registered(): void
    {
        foreach ([
            'tagore.cbt.index',
            'tagore.cbt.questions.store',
            'tagore.cbt.questions.approve',
            'tagore.cbt.papers.store',
            'tagore.cbt.papers.questions.store',
            'tagore.cbt.papers.publish',
            'tagore.cbt.start',
            'tagore.cbt.player',
            'tagore.cbt.answer',
            'tagore.cbt.submit',
            'tagore.cbt.result',
            'tagore.cbt.analytics',
        ] as $name) {
            $this->assertNotNull(app('router')->getRoutes()->getByName($name), "Missing route: {$name}");
        }
    }
}
