<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CbtEngineService
{
    public function createQuestion(int $institutionId, int $actorId, array $data): int
    {
        return DB::table('tagore_cbt_questions')->insertGetId([
            'institution_id' => $institutionId,
            'exam_track' => $data['exam_track'],
            'subject' => $data['subject'],
            'chapter' => $data['chapter'] ?? null,
            'topic' => $data['topic'] ?? null,
            'difficulty' => $data['difficulty'] ?? 'medium',
            'question_type' => $data['question_type'],
            'question_text' => $data['question_text'],
            'options_json' => isset($data['options']) ? json_encode($data['options']) : null,
            'correct_answer_json' => json_encode($data['correct_answer']),
            'explanation' => $data['explanation'] ?? null,
            'positive_marks' => $data['positive_marks'],
            'negative_marks' => $data['negative_marks'],
            'status' => 'pending_review',
            'created_by' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function approveQuestion(int $questionId, int $institutionId, int $actorId): void
    {
        $question = DB::table('tagore_cbt_questions')->where('id', $questionId)
            ->where('institution_id', $institutionId)->lockForUpdate()->first();
        abort_unless($question, 404);
        abort_unless($question->status === 'pending_review', 409, 'Only questions awaiting review can be approved.');
        DB::table('tagore_cbt_questions')->where('id', $questionId)->update([
            'status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function createMockTest(int $institutionId, int $actorId, array $data): int
    {
        return DB::table('tagore_cbt_mock_tests')->insertGetId([
            'institution_id' => $institutionId, 'code' => $data['code'],
            'title' => $data['title'], 'exam_track' => $data['exam_track'],
            'mode' => $data['mode'] ?? 'full_mock', 'duration_minutes' => $data['duration_minutes'],
            'attempt_limit' => $data['attempt_limit'] ?? 1, 'shuffle_questions' => $data['shuffle_questions'] ?? false,
            'shuffle_options' => $data['shuffle_options'] ?? false, 'allow_calculator' => $data['allow_calculator'] ?? false,
            'instructions_json' => isset($data['instructions']) ? json_encode($data['instructions']) : null,
            'available_from' => $data['available_from'] ?? null, 'available_until' => $data['available_until'] ?? null,
            'status' => 'draft', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function addSection(int $testId, string $name, string $subject, int $sortOrder): int
    {
        return DB::table('tagore_cbt_test_sections')->insertGetId([
            'mock_test_id' => $testId, 'name' => $name, 'subject' => $subject,
            'sort_order' => $sortOrder, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function attachQuestions(int $sectionId, int $institutionId, array $questionRows): void
    {
        DB::transaction(function () use ($sectionId, $institutionId, $questionRows) {
            $section = DB::table('tagore_cbt_test_sections as s')
                ->join('tagore_cbt_mock_tests as t', 't.id', '=', 's.mock_test_id')
                ->where('s.id', $sectionId)->where('t.institution_id', $institutionId)
                ->where('t.status', 'draft')->select('s.*', 't.exam_track')->first();
            abort_unless($section, 404);
            $order = (int) DB::table('tagore_cbt_test_questions')->where('section_id', $sectionId)->max('sort_order');
            foreach ($questionRows as $row) {
                $question = DB::table('tagore_cbt_questions')->where('id', $row['question_id'])
                    ->where('institution_id', $institutionId)->where('exam_track', $section->exam_track)
                    ->where('subject', $section->subject)->where('status', 'approved')->first();
                abort_unless($question, 422, 'Every selected question must be approved and match the test track and section subject.');
                DB::table('tagore_cbt_test_questions')->updateOrInsert(
                    ['section_id' => $sectionId, 'question_id' => $question->id],
                    ['sort_order' => ++$order, 'positive_marks' => $row['positive_marks'] ?? $question->positive_marks,
                        'negative_marks' => $row['negative_marks'] ?? $question->negative_marks,
                        'created_at' => now(), 'updated_at' => now()]
                );
            }
        });
    }

    public function publishMockTest(int $testId, int $institutionId): void
    {
        DB::transaction(function () use ($testId, $institutionId) {
            $test = DB::table('tagore_cbt_mock_tests')->where('id', $testId)
                ->where('institution_id', $institutionId)->lockForUpdate()->first();
            abort_unless($test, 404);
            abort_unless($test->status === 'draft', 409);
            $sections = DB::table('tagore_cbt_test_sections')->where('mock_test_id', $testId)->get();
            abort_if($sections->isEmpty(), 422, 'Add at least one subject section before publishing.');
            $questionCount = DB::table('tagore_cbt_test_questions as tq')
                ->join('tagore_cbt_test_sections as s', 's.id', '=', 'tq.section_id')
                ->where('s.mock_test_id', $testId)->count();
            abort_if($questionCount < 1, 422, 'Add approved questions before publishing.');
            foreach ($sections as $section) {
                $stats = DB::table('tagore_cbt_test_questions')->where('section_id', $section->id)
                    ->selectRaw('COUNT(*) as question_count, SUM(positive_marks) as total_marks')->first();
                DB::table('tagore_cbt_test_sections')->where('id', $section->id)->update([
                    'question_count' => (int) $stats->question_count,
                    'total_marks' => (float) $stats->total_marks,
                    'updated_at' => now(),
                ]);
            }
            $total = (float) DB::table('tagore_cbt_test_sections')->where('mock_test_id', $testId)->sum('total_marks');
            DB::table('tagore_cbt_mock_tests')->where('id', $testId)->update([
                'total_marks' => $total, 'status' => 'published', 'updated_at' => now(),
            ]);
        });
    }

    public function startAttempt(int $studentId, int $testId, int $institutionId): int
    {
        return DB::transaction(function () use ($studentId, $testId, $institutionId) {
            $test = DB::table('tagore_cbt_mock_tests')->where('id', $testId)
                ->where('institution_id', $institutionId)->lockForUpdate()->first();
            abort_unless($test && $test->status === 'published', 404);
            $now = now();
            abort_if($test->available_from && $now->lt($test->available_from), 403, 'This mock test is not open yet.');
            abort_if($test->available_until && $now->gt($test->available_until), 403, 'This mock test has closed.');
            $existing = DB::table('tagore_cbt_attempts')->where('mock_test_id', $testId)
                ->where('student_id', $studentId)->where('status', 'in_progress')->lockForUpdate()->first();
            if ($existing) return (int) $existing->id;
            $attemptNo = (int) DB::table('tagore_cbt_attempts')->where('mock_test_id', $testId)
                ->where('student_id', $studentId)->max('attempt_no') + 1;
            abort_if($attemptNo > (int) $test->attempt_limit, 403, 'You have used all attempts for this mock test.');
            $deadline = $now->copy()->addMinutes((int) $test->duration_minutes);
            if ($test->available_until && $deadline->gt($test->available_until)) $deadline = $test->available_until;
            $attemptId = DB::table('tagore_cbt_attempts')->insertGetId([
                'mock_test_id' => $testId, 'student_id' => $studentId, 'attempt_no' => $attemptNo,
                'started_at' => $now, 'deadline_at' => $deadline, 'status' => 'in_progress',
                'maximum_score' => $test->total_marks, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $questions = DB::table('tagore_cbt_test_questions as tq')
                ->join('tagore_cbt_test_sections as s', 's.id', '=', 'tq.section_id')
                ->join('tagore_cbt_questions as q', 'q.id', '=', 'tq.question_id')
                ->where('s.mock_test_id', $testId)->where('q.status', 'approved')
                ->orderBy('s.sort_order')->orderBy('tq.sort_order')->get(['tq.question_id', 'tq.section_id', 'tq.positive_marks', 'tq.negative_marks']);
            abort_if($questions->isEmpty(), 422, 'This mock test has no publishable questions.');
            if ($test->shuffle_questions) $questions = $questions->shuffle()->values();
            foreach ($questions as $index => $question) {
                $attemptQuestionId = DB::table('tagore_cbt_attempt_questions')->insertGetId([
                    'attempt_id' => $attemptId, 'question_id' => $question->question_id,
                    'section_id' => $question->section_id, 'sort_order' => $index + 1,
                    'positive_marks' => $question->positive_marks, 'negative_marks' => $question->negative_marks,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('tagore_cbt_attempt_answers')->insert([
                    'attempt_question_id' => $attemptQuestionId, 'visited' => false,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $this->event($attemptId, $studentId, 'started');
            return $attemptId;
        });
    }

    public function saveAnswer(int $studentId, int $attemptId, int $questionId, $answer, bool $markedForReview = false): void
    {
        DB::transaction(function () use ($studentId, $attemptId, $questionId, $answer, $markedForReview) {
            $attempt = DB::table('tagore_cbt_attempts')->where('id', $attemptId)
                ->where('student_id', $studentId)->lockForUpdate()->first();
            abort_unless($attempt, 404);
            abort_unless($attempt->status === 'in_progress', 409);
            abort_if(now()->gte($attempt->deadline_at), 409, 'The test deadline has passed. Submit the attempt to finalize it.');
            $attemptQuestion = DB::table('tagore_cbt_attempt_questions as aq')
                ->join('tagore_cbt_questions as q', 'q.id', '=', 'aq.question_id')
                ->where('aq.attempt_id', $attemptId)->where('aq.question_id', $questionId)
                ->first(['aq.id', 'q.question_type', 'q.options_json']);
            abort_unless($attemptQuestion, 404);
            $clean = $this->normalizeAnswer($attemptQuestion->question_type, $answer, $attemptQuestion->options_json);
            DB::table('tagore_cbt_attempt_answers')->where('attempt_question_id', $attemptQuestion->id)->update([
                'answer_json' => $clean === null ? null : json_encode($clean),
                'marked_for_review' => $markedForReview, 'visited' => true,
                'answered_at' => $clean === null ? null : now(), 'updated_at' => now(),
            ]);
            $this->event($attemptId, $studentId, 'answer_saved', $attemptQuestion->id);
        });
    }

    public function submitAttempt(int $studentId, int $attemptId): object
    {
        return DB::transaction(function () use ($studentId, $attemptId) {
            $attempt = DB::table('tagore_cbt_attempts')->where('id', $attemptId)
                ->where('student_id', $studentId)->lockForUpdate()->first();
            abort_unless($attempt, 404);
            if (in_array($attempt->status, ['submitted', 'expired'], true)) return $attempt;
            abort_unless($attempt->status === 'in_progress', 409);
            $rows = DB::table('tagore_cbt_attempt_questions as aq')
                ->join('tagore_cbt_questions as q', 'q.id', '=', 'aq.question_id')
                ->join('tagore_cbt_attempt_answers as a', 'a.attempt_question_id', '=', 'aq.id')
                ->where('aq.attempt_id', $attemptId)
                ->get(['aq.id', 'aq.positive_marks', 'aq.negative_marks', 'q.question_type', 'q.correct_answer_json', 'a.answer_json']);
            $score = 0.0; $correct = 0; $incorrect = 0; $unattempted = 0;
            foreach ($rows as $row) {
                $answer = $row->answer_json ? json_decode($row->answer_json, true) : null;
                if ($answer === null || $answer === '' || $answer === []) {
                    $unattempted++;
                    $awarded = 0.0; $isCorrect = null;
                } else {
                    $expected = json_decode($row->correct_answer_json, true);
                    $isCorrect = $this->answersMatch($row->question_type, $answer, $expected);
                    if ($isCorrect) { $correct++; $awarded = (float) $row->positive_marks; }
                    else { $incorrect++; $awarded = -(float) $row->negative_marks; }
                }
                $score = round($score + $awarded, 2);
                DB::table('tagore_cbt_attempt_answers')->where('attempt_question_id', $row->id)->update([
                    'is_correct' => $isCorrect, 'marks_awarded' => $awarded, 'updated_at' => now(),
                ]);
            }
            $status = now()->gt($attempt->deadline_at) ? 'expired' : 'submitted';
            DB::table('tagore_cbt_attempts')->where('id', $attemptId)->update([
                'status' => $status, 'submitted_at' => now(), 'score' => $score,
                'correct_count' => $correct, 'incorrect_count' => $incorrect,
                'unattempted_count' => $unattempted, 'updated_at' => now(),
            ]);
            $this->recalculateLeaderboard((int) $attempt->mock_test_id);
            $this->event($attemptId, $studentId, 'submitted');
            return DB::table('tagore_cbt_attempts')->where('id', $attemptId)->first();
        });
    }

    public function attemptForStudent(int $studentId, int $attemptId): object
    {
        $attempt = DB::table('tagore_cbt_attempts')->where('id', $attemptId)
            ->where('student_id', $studentId)->first();
        abort_unless($attempt, 404);
        return $attempt;
    }

    public function questionsForAttempt(int $studentId, int $attemptId)
    {
        $this->attemptForStudent($studentId, $attemptId);
        return DB::table('tagore_cbt_attempt_questions as aq')
            ->join('tagore_cbt_questions as q', 'q.id', '=', 'aq.question_id')
            ->join('tagore_cbt_attempt_answers as a', 'a.attempt_question_id', '=', 'aq.id')
            ->where('aq.attempt_id', $attemptId)->orderBy('aq.sort_order')
            ->get(['q.id', 'q.question_text', 'q.question_type', 'q.options_json',
                'aq.section_id', 'aq.sort_order', 'a.answer_json', 'a.marked_for_review', 'a.visited']);
    }

    private function normalizeAnswer(string $type, $answer, ?string $optionsJson)
    {
        if ($answer === null || $answer === '' || $answer === []) return null;
        $options = json_decode($optionsJson ?? '[]', true) ?: [];
        if ($type === 'single_choice') {
            $value = strtoupper(trim((string) $answer));
            abort_unless(array_key_exists($value, $options), 422, 'Select a valid answer option.');
            return $value;
        }
        if ($type === 'multiple_choice') {
            abort_unless(is_array($answer), 422, 'Select one or more answer options.');
            $values = array_values(array_unique(array_map(fn ($v) => strtoupper(trim((string) $v)), $answer)));
            abort_unless($values !== [] && count(array_diff($values, array_keys($options))) === 0, 422, 'One or more answer options are invalid.');
            sort($values);
            return $values;
        }
        if ($type === 'numeric') {
            abort_unless(is_numeric($answer), 422, 'Enter a numeric answer.');
            return round((float) $answer, 6);
        }
        throw ValidationException::withMessages(['answer' => 'Unsupported question type.']);
    }

    private function answersMatch(string $type, $answer, $expected): bool
    {
        if ($type === 'numeric') {
            $value = is_array($expected) ? (float) ($expected['value'] ?? NAN) : (float) $expected;
            $tolerance = is_array($expected) ? (float) ($expected['tolerance'] ?? 0.01) : 0.01;
            return is_numeric($answer) && abs((float) $answer - $value) <= $tolerance;
        }
        if ($type === 'multiple_choice') {
            $left = array_map('strval', (array) $answer); $right = array_map('strval', (array) $expected);
            sort($left); sort($right);
            return $left === $right;
        }
        return strtoupper((string) $answer) === strtoupper((string) $expected);
    }

    private function recalculateLeaderboard(int $testId): void
    {
        $attempts = DB::table('tagore_cbt_attempts')->where('mock_test_id', $testId)
            ->whereIn('status', ['submitted', 'expired'])->orderByDesc('score')->orderBy('submitted_at')->get();
        $count = $attempts->count();
        foreach ($attempts as $attempt) {
            $higher = $attempts->where('score', '>', $attempt->score)->count();
            $lower = $attempts->where('score', '<', $attempt->score)->count();
            $equal = $attempts->where('score', '=', $attempt->score)->count();
            $rank = $higher + 1;
            $percentile = $count ? round((($lower + (0.5 * $equal)) / $count) * 100, 3) : 0;
            DB::table('tagore_cbt_attempts')->where('id', $attempt->id)->update([
                'rank' => $rank, 'percentile' => $percentile, 'updated_at' => now(),
            ]);
        }
    }

    private function event(int $attemptId, int $studentId, string $type, ?int $questionId = null): void
    {
        DB::table('tagore_cbt_attempt_events')->insert([
            'attempt_id' => $attemptId, 'student_id' => $studentId,
            'event_type' => $type, 'attempt_question_id' => $questionId,
            'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
