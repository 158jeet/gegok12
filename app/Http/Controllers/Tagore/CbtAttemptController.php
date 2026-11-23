<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\CbtScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CbtAttemptController extends Controller
{
    private function context(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $roles = DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')
            ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
            ->pluck('r.code')->unique()->values();
        $institutionIds = $roles->contains('OWNER')
            ? DB::table('tagore_institutions')->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')->whereNotNull('institution_id')
                ->where(fn ($query) => $query->whereNull('start_date')->orWhereDate('start_date', '<=', today()))
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
                ->pluck('institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        abort_unless($institutionIds !== [], 403);
        return [$userId, $roles, $institutionIds];
    }

    private function assertCandidate($roles): void
    {
        abort_unless($roles->contains('STUDENT')
            && $roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER'])->isEmpty(), 403);
    }

    private function ownedAttempt(int $attemptId, int $userId, array $institutions)
    {
        $attempt = DB::table('tagore_cbt_attempts as a')
            ->join('tagore_cbt_exams as e', 'e.id', '=', 'a.exam_id')
            ->where('a.id', $attemptId)
            ->where('a.student_id', $userId)
            ->whereIn('e.institution_id', $institutions)
            ->select('a.*')
            ->first();
        abort_unless($attempt, 404);
        return $attempt;
    }

    private function examQuestions(int $examId)
    {
        return DB::table('tagore_cbt_exam_questions as eq')
            ->join('tagore_cbt_questions as q', 'q.id', '=', 'eq.question_id')
            ->where('eq.exam_id', $examId)
            ->orderBy('eq.display_order')
            ->get(['eq.id as exam_question_id', 'eq.section_code', 'eq.display_order', 'eq.marks', 'eq.negative_marks',
                'q.subject_code', 'q.chapter', 'q.difficulty', 'q.question_type', 'q.stem', 'q.options_json']);
    }

    private function persistAttemptRandomization(int $attemptId, int $examId, bool $shuffleQuestions, bool $shuffleOptions): void
    {
        $rows = DB::table('tagore_cbt_exam_questions as eq')
            ->join('tagore_cbt_questions as q', 'q.id', '=', 'eq.question_id')
            ->where('eq.exam_id', $examId)
            ->orderBy('eq.display_order')
            ->get(['eq.id as exam_question_id', 'q.options_json']);

        // Shuffle only within each section so the published section blueprint remains intact.
        $questionIds = [];
        foreach ($rows->groupBy('section_code') as $sectionRows) {
            $sectionIds = $sectionRows->pluck('exam_question_id')->map(fn ($id) => (int) $id)->all();
            if ($shuffleQuestions) {
                shuffle($sectionIds);
            }
            array_push($questionIds, ...$sectionIds);
        }

        $optionOrder = [];
        foreach ($rows as $row) {
            $keys = array_keys(json_decode($row->options_json ?? '[]', true) ?: []);
            if ($shuffleOptions) {
                shuffle($keys);
            }
            $optionOrder[(string) $row->exam_question_id] = $keys;
        }

        DB::table('tagore_cbt_attempts')->where('id', $attemptId)->update([
            'question_order_json' => json_encode($questionIds, JSON_THROW_ON_ERROR),
            'option_order_json' => json_encode($optionOrder, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    public function index(Request $request)
    {
        [, $roles] = $this->context($request);
        $this->assertCandidate($roles);
        return view('tagore.cbt.index');
    }

    public function available(Request $request)
    {
        [$userId, $roles, $institutions] = $this->context($request);
        $this->assertCandidate($roles);
        $now = now();
        $exams = DB::table('tagore_cbt_exams')->whereIn('institution_id', $institutions)->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderBy('starts_at')->get(['id', 'institution_id', 'title', 'exam_track', 'duration_minutes', 'starts_at', 'ends_at']);
        return response()->json(['exams' => $exams]);
    }

    public function start(Request $request, int $examId)
    {
        [$userId, $roles, $institutions] = $this->context($request);
        $this->assertCandidate($roles);
        $attempt = DB::transaction(function () use ($examId, $userId, $institutions) {
            $exam = DB::table('tagore_cbt_exams')->where('id', $examId)->whereIn('institution_id', $institutions)->lockForUpdate()->first();
            abort_unless($exam && $exam->status === 'published', 404);
            $now = now();
            abort_if($exam->starts_at && $now->lt($exam->starts_at), 409, 'This exam has not started.');
            abort_if($exam->ends_at && $now->gte($exam->ends_at), 409, 'The exam window has closed.');
            $existing = DB::table('tagore_cbt_attempts')->where('exam_id', $examId)->where('student_id', $userId)
                ->where('status', 'in_progress')->lockForUpdate()->first();
            if ($existing) {
                if (!$existing->question_order_json || !$existing->option_order_json) {
                    $this->persistAttemptRandomization((int) $existing->id, $examId, (bool) $exam->shuffle_questions, (bool) $exam->shuffle_options);
                    return DB::table('tagore_cbt_attempts')->where('id', $existing->id)->first();
                }
                return $existing;
            }
            $attemptNo = (int) DB::table('tagore_cbt_attempts')->where('exam_id', $examId)->where('student_id', $userId)->max('attempt_no') + 1;
            $expiresAt = $now->copy()->addMinutes((int) $exam->duration_minutes);
            if ($exam->ends_at && $expiresAt->gt($exam->ends_at)) $expiresAt = Carbon::parse($exam->ends_at);
            $id = DB::table('tagore_cbt_attempts')->insertGetId([
                'exam_id' => $examId, 'student_id' => $userId, 'attempt_no' => $attemptNo,
                'status' => 'in_progress', 'started_at' => $now, 'expires_at' => $expiresAt,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->persistAttemptRandomization($id, $examId, (bool) $exam->shuffle_questions, (bool) $exam->shuffle_options);
            return DB::table('tagore_cbt_attempts')->where('id', $id)->first();
        });
        return response()->json(['attempt_id' => $attempt->id, 'status' => $attempt->status, 'expires_at' => Carbon::parse($attempt->expires_at, config('app.timezone'))->toIso8601String(),
            'resumed' => $attempt->started_at !== $attempt->created_at]);
    }

    public function player(Request $request, int $attemptId)
    {
        [$userId, $roles, $institutions] = $this->context($request);
        $this->assertCandidate($roles);
        $attempt = $this->ownedAttempt($attemptId, $userId, $institutions);
        abort_unless($attempt->status === 'in_progress', 409);
        // Allow the candidate player to load an expired in-progress attempt so its countdown can trigger server-side submission.
        $exam = DB::table('tagore_cbt_exams')->where('id', $attempt->exam_id)->first(['id', 'title', 'exam_track', 'duration_minutes']);
        $attempt->expires_at = Carbon::parse($attempt->expires_at, config('app.timezone'))->toIso8601String();
        $questionOrder = json_decode($attempt->question_order_json ?? '[]', true) ?: [];
        $optionOrder = json_decode($attempt->option_order_json ?? '[]', true) ?: [];
        $questionRows = $this->examQuestions((int) $attempt->exam_id)->keyBy('exam_question_id');
        $orderedQuestions = $questionOrder !== []
            ? collect($questionOrder)->map(fn ($id) => $questionRows->get($id))->filter()
            : $questionRows->values();
        $questions = $orderedQuestions->map(function ($question) use ($optionOrder) {
            $options = json_decode($question->options_json ?? '[]', true) ?: [];
            $keys = $optionOrder[(string) $question->exam_question_id] ?? array_keys($options);
            $orderedOptions = [];
            foreach ($keys as $key) {
                if (array_key_exists($key, $options)) $orderedOptions[$key] = $options[$key];
            }
            foreach ($options as $key => $value) {
                if (!array_key_exists($key, $orderedOptions)) $orderedOptions[$key] = $value;
            }
            $question->options = $orderedOptions;
            unset($question->options_json);
            return $question;
        })->values();
        $responses = DB::table('tagore_cbt_responses')->where('attempt_id', $attemptId)
            ->get(['exam_question_id', 'answer_json', 'marked_for_review', 'time_spent_seconds'])
            ->mapWithKeys(fn ($response) => [(string) $response->exam_question_id => [
                'answer' => json_decode($response->answer_json ?? 'null', true),
                'marked_for_review' => (bool) $response->marked_for_review,
                'time_spent_seconds' => (int) $response->time_spent_seconds,
            ]]);
        return response()->json(['attempt' => $attempt, 'exam' => $exam, 'questions' => $questions, 'responses' => $responses]);
    }

    public function saveResponse(Request $request, int $attemptId)
    {
        [$userId, $roles, $institutions] = $this->context($request);
        $this->assertCandidate($roles);
        $data = $request->validate([
            'exam_question_id' => ['required', 'integer'],
            'answer' => ['nullable'],
            'marked_for_review' => ['sometimes', 'boolean'],
            'time_spent_seconds' => ['sometimes', 'integer', 'min:0', 'max:86400'],
        ]);
        $attempt = DB::transaction(function () use ($attemptId, $userId, $data, $institutions) {
            $attempt = DB::table('tagore_cbt_attempts as a')
                ->join('tagore_cbt_exams as e', 'e.id', '=', 'a.exam_id')
                ->where('a.id', $attemptId)->where('a.student_id', $userId)
                ->whereIn('e.institution_id', $institutions)
                ->select('a.*')->lockForUpdate()->first();
            abort_unless($attempt, 404);
            abort_unless($attempt->status === 'in_progress', 409);
            abort_if(now()->gte($attempt->expires_at), 409, 'The attempt time has expired.');
            abort_unless(DB::table('tagore_cbt_exam_questions')->where('id', $data['exam_question_id'])->where('exam_id', $attempt->exam_id)->exists(), 404);
            $question = DB::table('tagore_cbt_exam_questions as eq')
                ->join('tagore_cbt_questions as q', 'q.id', '=', 'eq.question_id')
                ->where('eq.id', $data['exam_question_id'])->where('eq.exam_id', $attempt->exam_id)
                ->first(['q.question_type', 'q.options_json']);
            abort_unless($question, 404);
            $answer = $data['answer'] ?? null;
            $options = json_decode($question->options_json ?? '[]', true) ?: [];
            if ($answer !== null && $answer !== '') {
                if ($question->question_type === 'single_choice') {
                    if (!is_string($answer) || !array_key_exists($answer, $options)) {
                        throw ValidationException::withMessages(['answer' => 'Select one of the available options.']);
                    }
                } elseif ($question->question_type === 'multiple_choice') {
                    if (!is_array($answer) || count($answer) > 20 || count($answer) !== count(array_unique($answer, SORT_REGULAR))) {
                        throw ValidationException::withMessages(['answer' => 'Select a valid set of options.']);
                    }
                    foreach ($answer as $choice) {
                        if (!is_string($choice) || !array_key_exists($choice, $options)) {
                            throw ValidationException::withMessages(['answer' => 'One or more selected options are invalid.']);
                        }
                    }
                } elseif ($question->question_type === 'numerical') {
                    if (!is_scalar($answer) || !is_numeric($answer) || strlen((string) $answer) > 32) {
                        throw ValidationException::withMessages(['answer' => 'Enter a valid numerical answer.']);
                    }
                } else {
                    throw ValidationException::withMessages(['answer' => 'This question type is not supported.']);
                }
            }
            $responseQuery = DB::table('tagore_cbt_responses')
                ->where('attempt_id', $attemptId)->where('exam_question_id', $data['exam_question_id']);
            $responseValues = [
                'answer_json' => $answer === null ? null : json_encode($answer, JSON_THROW_ON_ERROR),
                'marked_for_review' => (bool) ($data['marked_for_review'] ?? false),
                'time_spent_seconds' => (int) ($data['time_spent_seconds'] ?? 0),
                'updated_at' => now(),
            ];
            if ($responseQuery->exists()) {
                $responseQuery->update($responseValues);
            } else {
                DB::table('tagore_cbt_responses')->insert($responseValues + [
                    'attempt_id' => $attemptId,
                    'exam_question_id' => $data['exam_question_id'],
                    'created_at' => now(),
                ]);
            }
            return $attempt;
        });
        return response()->json(['saved' => true, 'expires_at' => Carbon::parse($attempt->expires_at, config('app.timezone'))->toIso8601String()]);
    }

    public function submit(Request $request, int $attemptId, CbtScoringService $scorer)
    {
        [$userId, $roles, $institutions] = $this->context($request);
        $this->assertCandidate($roles);
        $result = DB::transaction(function () use ($attemptId, $userId, $scorer, $institutions) {
            $attempt = DB::table('tagore_cbt_attempts as a')
                ->join('tagore_cbt_exams as e', 'e.id', '=', 'a.exam_id')
                ->where('a.id', $attemptId)->where('a.student_id', $userId)
                ->whereIn('e.institution_id', $institutions)
                ->select('a.*')->lockForUpdate()->first();
            abort_unless($attempt, 404);
            if ($attempt->status === 'submitted') return $this->resultPayload($attempt);
            abort_unless($attempt->status === 'in_progress', 409);
            $questions = $this->examQuestions((int) $attempt->exam_id)->map(fn ($q) => (array) $q)->all();
            $stored = DB::table('tagore_cbt_responses')->where('attempt_id', $attemptId)->get()->keyBy('exam_question_id');
            $responses = [];
            foreach ($stored as $questionId => $response) $responses[(string) $questionId] = json_decode($response->answer_json ?? 'null', true);
            $scored = $scorer->score($questions, $responses);
            foreach ($scored['details'] as $questionId => $detail) {
                DB::table('tagore_cbt_responses')->where('attempt_id', $attemptId)->where('exam_question_id', $questionId)
                    ->update(['is_correct' => $detail['is_correct'], 'awarded_marks' => $detail['awarded_marks'], 'updated_at' => now()]);
            }
            DB::table('tagore_cbt_attempts')->where('id', $attemptId)->update([
                'status' => 'submitted', 'submitted_at' => now(), 'score' => $scored['score'], 'max_score' => $scored['max_score'],
                'accuracy' => $scored['accuracy'], 'correct_count' => $scored['correct_count'],
                'wrong_count' => $scored['wrong_count'], 'unanswered_count' => $scored['unanswered_count'], 'updated_at' => now(),
            ]);
            $submittedAttempt = DB::table('tagore_cbt_attempts')->where('id', $attemptId)->first();
            return $this->resultPayload($submittedAttempt);
        });
        return response()->json(['submitted' => true, 'result' => $result]);
    }

    public function result(Request $request, int $attemptId)
    {
        [$userId, $roles, $institutions] = $this->context($request);
        $this->assertCandidate($roles);
        $attempt = $this->ownedAttempt($attemptId, $userId, $institutions);
        abort_unless($attempt->status === 'submitted', 409);
        return response()->json(['result' => $this->resultPayload($attempt)]);
    }

    private function resultPayload($attempt): array
    {
        $population = DB::table('tagore_cbt_attempts')->where('exam_id', $attempt->exam_id)->where('status', 'submitted');
        $total = (int) (clone $population)->count();
        $rank = (int) (clone $population)->where(function ($q) use ($attempt) {
            $q->where('score', '>', $attempt->score)->orWhere(function ($tie) use ($attempt) {
                $tie->where('score', '=', $attempt->score)->where('submitted_at', '<', $attempt->submitted_at);
            });
        })->count() + 1;
        return ['score' => (float) $attempt->score, 'max_score' => (float) $attempt->max_score,
            'accuracy' => (float) $attempt->accuracy, 'correct_count' => (int) $attempt->correct_count,
            'wrong_count' => (int) $attempt->wrong_count, 'unanswered_count' => (int) $attempt->unanswered_count,
            'rank' => $rank, 'candidates' => $total,
            'percentile' => $total > 1 ? round((($total - $rank) / ($total - 1)) * 100, 2) : 100.0];
    }
}
