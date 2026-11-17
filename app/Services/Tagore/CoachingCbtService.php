<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CoachingCbtService
{
    public function createQuestion(int $institutionId, int $actorId, array $data): int
    {
        $code = $data['question_code'] ?? ('CBT-' . strtoupper(bin2hex(random_bytes(5))));
        $version = (int) DB::table('tagore_cbt_questions')
            ->where('institution_id', $institutionId)
            ->where('question_code', $code)
            ->max('version') + 1;

        return (int) DB::table('tagore_cbt_questions')->insertGetId([
            'institution_id' => $institutionId,
            'question_code' => $code,
            'subject' => strtoupper($data['subject']),
            'chapter' => trim($data['chapter']),
            'topic' => $data['topic'] ?? null,
            'difficulty' => $data['difficulty'],
            'question_type' => $data['question_type'],
            'stem' => $data['stem'],
            'options_json' => isset($data['options']) ? json_encode($data['options']) : null,
            'answer_json' => json_encode($data['answer']),
            'explanation' => $data['explanation'] ?? null,
            'default_marks' => $data['default_marks'] ?? 4,
            'default_negative_marks' => $data['default_negative_marks'] ?? 1,
            'version' => $version,
            'status' => 'draft',
            'created_by' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function approveQuestion(int $questionId): void
    {
        DB::transaction(function () use ($questionId) {
            $question = DB::table('tagore_cbt_questions')->where('id', $questionId)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_unless($question, 404);
            abort_unless($question->status === 'draft', 409, 'Only draft questions can be approved.');
            DB::table('tagore_cbt_questions')->where('id', $questionId)->update(['status' => 'approved', 'updated_at' => now()]);
        });
    }

    public function createPaper(int $institutionId, int $actorId, array $data): int
    {
        return DB::transaction(function () use ($institutionId, $actorId, $data) {
            $paperId = DB::table('tagore_cbt_papers')->insertGetId([
                'institution_id' => $institutionId,
                'name' => $data['name'],
                'exam_type' => $data['exam_type'],
                'duration_minutes' => $data['duration_minutes'],
                'max_attempts' => $data['max_attempts'] ?? 1,
                'instructions' => $data['instructions'] ?? null,
                'shuffle_questions' => (bool) ($data['shuffle_questions'] ?? true),
                'shuffle_options' => (bool) ($data['shuffle_options'] ?? false),
                'available_from' => $data['available_from'] ?? null,
                'available_until' => $data['available_until'] ?? null,
                'status' => 'draft',
                'created_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($data['sections'] as $index => $section) {
                DB::table('tagore_cbt_sections')->insert([
                    'paper_id' => $paperId,
                    'section_key' => strtoupper($section['section_key']),
                    'title' => $section['title'],
                    'sort_order' => $index + 1,
                    'question_limit' => $section['question_limit'] ?? null,
                    'required_answer_count' => $section['required_answer_count'] ?? null,
                    'section_type' => $section['section_type'] ?? 'mixed',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->event($paperId, null, $actorId, 'paper_created');
            return (int) $paperId;
        });
    }

    public function addQuestionToPaper(int $paperId, int $sectionId, int $questionId, ?float $marks = null, ?float $negativeMarks = null): void
    {
        DB::transaction(function () use ($paperId, $sectionId, $questionId, $marks, $negativeMarks) {
            $paper = DB::table('tagore_cbt_papers')->where('id', $paperId)->lockForUpdate()->first();
            abort_unless($paper && $paper->status === 'draft', 409, 'Questions can only be added to a draft paper.');
            $section = DB::table('tagore_cbt_sections')->where('id', $sectionId)->where('paper_id', $paperId)->first();
            abort_unless($section, 422, 'Section does not belong to this paper.');
            $question = DB::table('tagore_cbt_questions')->where('id', $questionId)
                ->where('institution_id', $paper->institution_id)->where('status', 'approved')
                ->whereNull('deleted_at')->first();
            abort_unless($question, 422, 'Only approved questions from the same institution can be added.');
            abort_unless(
                $section->section_type === 'mixed' || $section->section_type === $question->question_type,
                422,
                'Question type does not match the selected paper section.'
            );

            $exists = DB::table('tagore_cbt_paper_questions')->where('paper_id', $paperId)->where('question_id', $questionId)->exists();
            abort_if($exists, 409, 'This question is already in the paper.');

            $snapshot = [
                'question_id' => (int) $question->id,
                'question_code' => $question->question_code,
                'version' => (int) $question->version,
                'subject' => $question->subject,
                'chapter' => $question->chapter,
                'topic' => $question->topic,
                'difficulty' => $question->difficulty,
                'question_type' => $question->question_type,
                'stem' => $question->stem,
                'options' => $question->options_json ? json_decode($question->options_json, true) : null,
                'correct_answer' => json_decode($question->answer_json, true),
                'explanation' => $question->explanation,
            ];
            $order = (int) DB::table('tagore_cbt_paper_questions')->where('paper_id', $paperId)->where('section_id', $sectionId)->max('sort_order') + 1;
            DB::table('tagore_cbt_paper_questions')->insert([
                'paper_id' => $paperId,
                'section_id' => $sectionId,
                'question_id' => $questionId,
                'sort_order' => $order,
                'marks' => $marks ?? $question->default_marks,
                'negative_marks' => $negativeMarks ?? $question->default_negative_marks,
                'question_snapshot_json' => json_encode($snapshot),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event($paperId, null, null, 'question_added', ['question_id' => $questionId, 'section_id' => $sectionId]);
        });
    }

    public function publishPaper(int $paperId, int $actorId): void
    {
        DB::transaction(function () use ($paperId, $actorId) {
            $paper = DB::table('tagore_cbt_papers')->where('id', $paperId)->lockForUpdate()->first();
            abort_unless($paper && $paper->status === 'draft', 409, 'Only draft papers can be published.');
            $sections = DB::table('tagore_cbt_sections')->where('paper_id', $paperId)->get();
            $questions = DB::table('tagore_cbt_paper_questions')->where('paper_id', $paperId)->get()->groupBy('section_id');
            abort_if($sections->isEmpty(), 422, 'Add at least one section before publishing.');
            abort_if(DB::table('tagore_cbt_paper_questions')->where('paper_id', $paperId)->doesntExist(), 422, 'Add questions before publishing.');

            foreach ($sections as $section) {
                $count = $questions->get($section->id, collect())->count();
                abort_if($section->question_limit !== null && $count > (int) $section->question_limit, 422, 'A section exceeds its configured question limit.');
                abort_if($section->required_answer_count !== null && (int) $section->required_answer_count > $count, 422, 'A section requires more answers than it contains questions.');
            }

            DB::table('tagore_cbt_papers')->where('id', $paperId)->update([
                'status' => 'published',
                'published_by' => $actorId,
                'published_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event($paperId, null, $actorId, 'paper_published');
        });
    }

    public function startAttempt(int $paperId, int $studentId): int
    {
        return DB::transaction(function () use ($paperId, $studentId) {
            $paper = DB::table('tagore_cbt_papers')->where('id', $paperId)->lockForUpdate()->first();
            abort_unless($paper && $paper->status === 'published', 404);
            abort_if($paper->available_from && now()->lt($paper->available_from), 409, 'This mock test is not open yet.');
            abort_if($paper->available_until && now()->gt($paper->available_until), 409, 'This mock test has closed.');

            $student = DB::table('users')->where('id', $studentId)->where('usergroup_id', 6)->whereNull('deleted_at')->first(['id', 'school_id']);
            abort_unless($student, 403);
            $institution = DB::table('tagore_institutions')->where('id', $paper->institution_id)->where('school_id', $student->school_id)->where('status', 'active')->first();
            abort_unless($institution, 403, 'Student is not enrolled in this coaching institution.');

            $existing = DB::table('tagore_cbt_attempts')->where('paper_id', $paperId)->where('student_id', $studentId)->where('status', 'in_progress')->lockForUpdate()->first();
            if ($existing && now()->lte($existing->expires_at)) {
                return (int) $existing->id;
            }
            if ($existing) {
                $this->scoreAttempt((int) $existing->id, true);
            }

            $attemptCount = DB::table('tagore_cbt_attempts')->where('paper_id', $paperId)->where('student_id', $studentId)->count();
            abort_if($attemptCount >= (int) $paper->max_attempts, 409, 'You have used all attempts for this mock test.');

            $paperQuestions = DB::table('tagore_cbt_paper_questions as pq')
                ->join('tagore_cbt_sections as s', 's.id', '=', 'pq.section_id')
                ->where('pq.paper_id', $paperId)
                ->orderBy('s.sort_order')->orderBy('pq.sort_order')
                ->get(['pq.question_id', 's.section_key', 's.sort_order as section_order', 'pq.sort_order']);
            abort_if($paperQuestions->isEmpty(), 409, 'This mock test has no questions.');

            // Preserve section order; randomize only within each section.
            $questionOrder = [];
            foreach ($paperQuestions->groupBy('section_order') as $sectionRows) {
                $sectionQuestionIds = $sectionRows->pluck('question_id')->map(fn ($id) => (int) $id)->all();
                if ($paper->shuffle_questions) {
                    shuffle($sectionQuestionIds);
                }
                $questionOrder = array_merge($questionOrder, $sectionQuestionIds);
            }
            $attemptNo = $attemptCount + 1;
            $attemptId = DB::table('tagore_cbt_attempts')->insertGetId([
                'paper_id' => $paperId,
                'institution_id' => $paper->institution_id,
                'student_id' => $studentId,
                'attempt_no' => $attemptNo,
                'started_at' => now(),
                'expires_at' => now()->addMinutes((int) $paper->duration_minutes),
                'status' => 'in_progress',
                'question_order_json' => json_encode($questionOrder),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->event($paperId, $attemptId, $studentId, 'attempt_started');
            return (int) $attemptId;
        });
    }

    public function saveAnswer(int $attemptId, int $studentId, int $questionId, $answer, bool $marked, int $timeSpent): void
    {
        DB::transaction(function () use ($attemptId, $studentId, $questionId, $answer, $marked, $timeSpent) {
            $attempt = DB::table('tagore_cbt_attempts')->where('id', $attemptId)->where('student_id', $studentId)->lockForUpdate()->first();
            abort_unless($attempt, 404);
            abort_unless($attempt->status === 'in_progress' && now()->lte($attempt->expires_at), 409, 'This attempt is no longer accepting answers.');
            $questionIds = json_decode($attempt->question_order_json, true) ?: [];
            abort_unless(in_array($questionId, $questionIds, true), 422, 'Question does not belong to this attempt.');
            $paperQuestion = DB::table('tagore_cbt_paper_questions')
                ->where('paper_id', $attempt->paper_id)
                ->where('question_id', $questionId)
                ->first(['question_snapshot_json']);
            abort_unless($paperQuestion, 422, 'Question is not part of this paper.');
            if ($answer !== null && $answer !== '' && $answer !== []) {
                $snapshot = json_decode($paperQuestion->question_snapshot_json, true) ?: [];
                $this->validateAnswer($snapshot, $answer);
            }

            DB::table('tagore_cbt_answers')->updateOrInsert(
                ['attempt_id' => $attemptId, 'question_id' => $questionId],
                [
                    'answer_json' => $answer === null ? null : json_encode($answer),
                    'is_marked_for_review' => $marked,
                    'is_visited' => true,
                    'time_spent_seconds' => max(0, $timeSpent),
                    'answered_at' => $answer === null || $answer === '' || $answer === [] ? null : now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $this->event((int) $attempt->paper_id, $attemptId, $studentId, 'answer_saved', ['question_id' => $questionId]);
        });
    }

    public function scoreAttempt(int $attemptId, bool $expired = false): object
    {
        return DB::transaction(function () use ($attemptId, $expired) {
            $attempt = DB::table('tagore_cbt_attempts')->where('id', $attemptId)->lockForUpdate()->first();
            abort_unless($attempt, 404);
            if (in_array($attempt->status, ['submitted', 'expired'], true)) {
                return $attempt;
            }

            $paperQuestions = DB::table('tagore_cbt_paper_questions as pq')
                ->join('tagore_cbt_sections as s', 's.id', '=', 'pq.section_id')
                ->where('pq.paper_id', $attempt->paper_id)
                ->get(['pq.*', 's.required_answer_count', 's.section_key'])
                ->keyBy('question_id');
            $answers = DB::table('tagore_cbt_answers')->where('attempt_id', $attemptId)->get()->keyBy('question_id');
            $score = 0.0;
            $maxScore = 0.0;
            $correct = 0;
            $incorrect = 0;
            $unanswered = 0;
            $sectionRows = [];
            $topicStats = [];

            foreach (json_decode($attempt->question_order_json, true) ?: [] as $questionId) {
                $row = $paperQuestions->get($questionId);
                if ($row) {
                    $sectionRows[$row->section_id][] = ['question_id' => $questionId, 'row' => $row];
                    $snapshot = json_decode($row->question_snapshot_json, true) ?: [];
                    $subject = (string) ($snapshot['subject'] ?? 'UNKNOWN');
                    $chapter = (string) ($snapshot['chapter'] ?? 'Unclassified');
                    $topic = (string) ($snapshot['topic'] ?? '');
                    $topicKey = $subject.'|'.$chapter.'|'.$topic;
                    if (!isset($topicStats[$topicKey])) {
                        $topicStats[$topicKey] = [
                            'subject' => $subject, 'chapter' => $chapter, 'topic' => $topic,
                            'total_questions' => 0, 'attempted_count' => 0, 'scored_count' => 0,
                            'correct_count' => 0, 'incorrect_count' => 0, 'score' => 0.0,
                        ];
                    }
                    $topicStats[$topicKey]['total_questions']++;
                    $answerRow = $answers->get($questionId);
                    $answer = $answerRow && $answerRow->answer_json !== null ? json_decode($answerRow->answer_json, true) : null;
                    if ($answer !== null && $answer !== '' && $answer !== []) {
                        $topicStats[$topicKey]['attempted_count']++;
                    }
                }
            }

            foreach ($sectionRows as $rows) {
                $required = $rows[0]['row']->required_answer_count === null
                    ? count($rows)
                    : min(count($rows), (int) $rows[0]['row']->required_answer_count);

                // The maximum score for an optional section is based on its best
                // required-answer combination, not every available question.
                $possibleMarks = array_map(fn ($item) => (float) $item['row']->marks, $rows);
                rsort($possibleMarks);
                $maxScore += array_sum(array_slice($possibleMarks, 0, $required));

                $answeredRows = [];
                foreach ($rows as $position => $item) {
                    $answerRow = $answers->get($item['question_id']);
                    $answer = $answerRow && $answerRow->answer_json !== null ? json_decode($answerRow->answer_json, true) : null;
                    if ($answer !== null && $answer !== '' && $answer !== []) {
                        $answeredRows[] = ['item' => $item, 'answer' => $answer, 'answered_at' => $answerRow->answered_at, 'position' => $position];
                    }
                }

                // When a section says "answer any N", only the first N attempted
                // questions are scored. Extra responses are retained but ignored.
                usort($answeredRows, function ($a, $b) {
                    $timeCompare = strcmp((string) $a['answered_at'], (string) $b['answered_at']);
                    return $timeCompare !== 0 ? $timeCompare : ($a['position'] <=> $b['position']);
                });
                $scoredRows = array_slice($answeredRows, 0, $required);
                $unanswered += max(0, $required - count($scoredRows));

                foreach ($scoredRows as $scored) {
                    $row = $scored['item']['row'];
                    $snapshot = json_decode($row->question_snapshot_json, true) ?: [];
                    $marks = (float) $row->marks;
                    $negative = (float) $row->negative_marks;
                    $subject = (string) ($snapshot['subject'] ?? 'UNKNOWN');
                    $chapter = (string) ($snapshot['chapter'] ?? 'Unclassified');
                    $topic = (string) ($snapshot['topic'] ?? '');
                    $topicKey = $subject.'|'.$chapter.'|'.$topic;
                    $topicStats[$topicKey]['scored_count']++;
                    if ($this->isCorrect((string) ($snapshot['question_type'] ?? ''), $scored['answer'], $snapshot['correct_answer'] ?? null)) {
                        $score += $marks;
                        $correct++;
                        $topicStats[$topicKey]['correct_count']++;
                        $topicStats[$topicKey]['score'] += $marks;
                    } else {
                        $score -= $negative;
                        $incorrect++;
                        $topicStats[$topicKey]['incorrect_count']++;
                        $topicStats[$topicKey]['score'] -= $negative;
                    }
                }
            }

            foreach ($topicStats as $stats) {
                DB::table('tagore_cbt_attempt_topic_stats')->insert([
                    'attempt_id' => $attemptId,
                    'subject' => $stats['subject'],
                    'chapter' => $stats['chapter'],
                    'topic' => $stats['topic'],
                    'total_questions' => $stats['total_questions'],
                    'attempted_count' => $stats['attempted_count'],
                    'scored_count' => $stats['scored_count'],
                    'correct_count' => $stats['correct_count'],
                    'incorrect_count' => $stats['incorrect_count'],
                    'unattempted_count' => max(0, $stats['total_questions'] - $stats['attempted_count']),
                    'score' => round($stats['score'], 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $expired = $expired || now()->gt($attempt->expires_at);
            $status = $expired ? 'expired' : 'submitted';
            $submittedAt = now();
            DB::table('tagore_cbt_attempts')->where('id', $attemptId)->update([
                'status' => $status,
                'submitted_at' => $submittedAt,
                'score' => round($score, 2),
                'max_score' => round($maxScore, 2),
                'correct_count' => $correct,
                'incorrect_count' => $incorrect,
                'unanswered_count' => $unanswered,
                'updated_at' => $submittedAt,
            ]);
            $this->event((int) $attempt->paper_id, $attemptId, (int) $attempt->student_id, $expired ? 'attempt_expired' : 'attempt_submitted', [
                'score' => round($score, 2), 'correct' => $correct, 'incorrect' => $incorrect, 'unanswered' => $unanswered,
            ]);

            $this->refreshRanks((int) $attempt->paper_id);
            return DB::table('tagore_cbt_attempts')->where('id', $attemptId)->first();
        });
    }

    public function refreshRanks(int $paperId): void
    {
        $attempts = DB::table('tagore_cbt_attempts')->where('paper_id', $paperId)->whereIn('status', ['submitted', 'expired'])->orderByDesc('score')->orderBy('submitted_at')->get();
        $count = $attempts->count();
        foreach ($attempts as $attempt) {
            $rank = 1 + $attempts->where('score', '>', $attempt->score)->count();
            $percentile = $count <= 1 ? 100 : round((($count - $rank) / ($count - 1)) * 100, 4);
            DB::table('tagore_cbt_attempts')->where('id', $attempt->id)->update([
                'rank' => $rank,
                'percentile' => $percentile,
                'updated_at' => now(),
            ]);
        }
    }

    public function playerPayload(int $attemptId, int $studentId): array
    {
        $attempt = DB::table('tagore_cbt_attempts as a')
            ->join('tagore_cbt_papers as p', 'p.id', '=', 'a.paper_id')
            ->where('a.id', $attemptId)->where('a.student_id', $studentId)
            ->first(['a.*', 'p.name as paper_name', 'p.instructions', 'p.duration_minutes', 'p.shuffle_options']);
        abort_unless($attempt, 404);

        $questionRows = DB::table('tagore_cbt_paper_questions as pq')
            ->join('tagore_cbt_sections as s', 's.id', '=', 'pq.section_id')
            ->where('pq.paper_id', $attempt->paper_id)
            ->get(['pq.*', 's.title as section_title', 's.section_key', 's.required_answer_count'])
            ->keyBy('question_id');
        $sectionCounts = DB::table('tagore_cbt_paper_questions')
            ->where('paper_id', $attempt->paper_id)
            ->select('section_id', DB::raw('COUNT(*) as question_count'))
            ->groupBy('section_id')
            ->pluck('question_count', 'section_id');
        $saved = DB::table('tagore_cbt_answers')->where('attempt_id', $attemptId)->get()->keyBy('question_id');
        $questions = [];
        foreach (json_decode($attempt->question_order_json, true) ?: [] as $questionId) {
            $row = $questionRows->get($questionId);
            if (!$row) {
                continue;
            }
            $snapshot = json_decode($row->question_snapshot_json, true) ?: [];
            if ($attempt->shuffle_options && is_array($snapshot['options'] ?? null)) {
                $optionKeys = array_keys($snapshot['options']);
                usort($optionKeys, fn ($a, $b) => strcmp(
                    hash('sha256', $attemptId.'-'.$questionId.'-'.$a),
                    hash('sha256', $attemptId.'-'.$questionId.'-'.$b)
                ));
                $shuffledOptions = [];
                foreach ($optionKeys as $optionKey) {
                    $shuffledOptions[$optionKey] = $snapshot['options'][$optionKey];
                }
                $snapshot['options'] = $shuffledOptions;
            }
            unset($snapshot['correct_answer'], $snapshot['explanation']);
            $answerRow = $saved->get($questionId);
            $questions[] = [
                'id' => (int) $questionId,
                'section_id' => (int) $row->section_id,
                'section_title' => $row->section_title,
                'section_key' => $row->section_key,
                'required_answer_count' => $row->required_answer_count === null ? null : (int) $row->required_answer_count,
                'section_question_count' => (int) ($sectionCounts[$row->section_id] ?? 0),
                'marks' => (float) $row->marks,
                'negative_marks' => (float) $row->negative_marks,
                'question' => $snapshot,
                'answer' => $answerRow && $answerRow->answer_json !== null ? json_decode($answerRow->answer_json, true) : null,
                'marked_for_review' => (bool) ($answerRow->is_marked_for_review ?? false),
            ];
        }

        return ['attempt' => $attempt, 'questions' => $questions];
    }

    private function validateAnswer(array $snapshot, $answer): void
    {
        $type = (string) ($snapshot['question_type'] ?? '');
        $options = $snapshot['options'] ?? [];

        if ($type === 'numerical') {
            $value = is_array($answer) ? ($answer['value'] ?? null) : $answer;
            if (!is_numeric($value)) {
                throw ValidationException::withMessages(['answer' => 'Enter a valid numerical answer.']);
            }
            return;
        }

        $validKeys = array_map('strval', array_keys(is_array($options) ? $options : []));
        if ($type === 'multiple_choice') {
            if (!is_array($answer) || !$answer) {
                throw ValidationException::withMessages(['answer' => 'Select at least one option.']);
            }
            $selected = array_map('strval', $answer);
            if (count($selected) !== count(array_unique($selected)) || array_diff($selected, $validKeys)) {
                throw ValidationException::withMessages(['answer' => 'One or more selected options are invalid.']);
            }
            return;
        }

        if (!is_scalar($answer) || !in_array((string) $answer, $validKeys, true)) {
            throw ValidationException::withMessages(['answer' => 'Select a valid answer option.']);
        }
    }

    private function isCorrect(string $type, $given, $expected): bool
    {
        if ($type === 'multiple_choice') {
            $given = array_map('strval', is_array($given) ? $given : [$given]);
            $expected = array_map('strval', is_array($expected) ? $expected : [$expected]);
            sort($given);
            sort($expected);
            return $given === $expected;
        }
        if ($type === 'numerical') {
            $givenValue = is_array($given) ? ($given['value'] ?? null) : $given;
            $expectedValue = is_array($expected) ? ($expected['value'] ?? null) : $expected;
            $tolerance = is_array($expected) ? (float) ($expected['tolerance'] ?? 0.01) : 0.01;
            return is_numeric($givenValue) && is_numeric($expectedValue)
                && abs((float) $givenValue - (float) $expectedValue) <= $tolerance;
        }

        return is_scalar($given) && is_scalar($expected)
            && mb_strtolower(trim((string) $given)) === mb_strtolower(trim((string) $expected));
    }

    private function event(int $paperId, ?int $attemptId, ?int $userId, string $event, array $details = []): void
    {
        DB::table('tagore_cbt_events')->insert([
            'paper_id' => $paperId,
            'attempt_id' => $attemptId,
            'user_id' => $userId,
            'event' => $event,
            'details_json' => $details ? json_encode($details) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
