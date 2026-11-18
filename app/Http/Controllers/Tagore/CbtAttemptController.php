<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\CbtScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CbtAttemptController extends Controller
{
    private function context(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $roles = DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')->pluck('r.code')->unique()->values();
        $institutionIds = $roles->contains('OWNER')
            ? DB::table('tagore_institutions')->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')->whereNotNull('institution_id')
                ->pluck('institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        abort_unless($institutionIds !== [], 403);
        return [$userId, $roles, $institutionIds];
    }

    private function assertCandidate($roles): void
    {
        abort_unless($roles->contains('STUDENT')
            && $roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER'])->isEmpty(), 403);
    }

    private function ownedAttempt(int $attemptId, int $userId)
    {
        $attempt = DB::table('tagore_cbt_attempts')->where('id', $attemptId)->where('student_id', $userId)->first();
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
            if ($existing) return $existing;
            $attemptNo = (int) DB::table('tagore_cbt_attempts')->where('exam_id', $examId)->where('student_id', $userId)->max('attempt_no') + 1;
            $expiresAt = $now->copy()->addMinutes((int) $exam->duration_minutes);
            if ($exam->ends_at && $expiresAt->gt($exam->ends_at)) $expiresAt = \Illuminate\Support\Carbon::parse($exam->ends_at);
            $id = DB::table('tagore_cbt_attempts')->insertGetId([
                'exam_id' => $examId, 'student_id' => $userId, 'attempt_no' => $attemptNo,
                'status' => 'in_progress', 'started_at' => $now, 'expires_at' => $expiresAt,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            return DB::table('tagore_cbt_attempts')->where('id', $id)->first();
        });
        return response()->json(['attempt_id' => $attempt->id, 'status' => $attempt->status, 'expires_at' => $attempt->expires_at,
            'resumed' => $attempt->started_at !== $attempt->created_at]);
    }

    public function player(Request $request, int $attemptId)
    {
        [$userId, $roles] = $this->context($request);
        $this->assertCandidate($roles);
        $attempt = $this->ownedAttempt($attemptId, $userId);
        abort_unless($attempt->status === 'in_progress', 409);
        abort_if(now()->gte($attempt->expires_at), 409, 'The attempt time has expired. Submit the attempt to calculate its result.');
        $exam = DB::table('tagore_cbt_exams')->where('id', $attempt->exam_id)->first(['id', 'title', 'exam_track', 'duration_minutes']);
        $questions = $this->examQuestions((int) $attempt->exam_id)->map(function ($question) {
            $question->options = json_decode($question->options_json ?? '[]', true) ?: [];
            unset($question->options_json);
            return $question;
        });
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
        [$userId, $roles] = $this->context($request);
        $this->assertCandidate($roles);
        $data = $request->validate([
            'exam_question_id' => ['required', 'integer'],
            'answer' => ['nullable'],
            'marked_for_review' => ['sometimes', 'boolean'],
            'time_spent_seconds' => ['sometimes', 'integer', 'min:0', 'max:86400'],
        ]);
        $attempt = DB::transaction(function () use ($attemptId, $userId, $data) {
            $attempt = DB::table('tagore_cbt_attempts')->where('id', $attemptId)->where('student_id', $userId)->lockForUpdate()->first();
            abort_unless($attempt, 404);
            abort_unless($attempt->status === 'in_progress', 409);
            abort_if(now()->gte($attempt->expires_at), 409, 'The attempt time has expired.');
            abort_unless(DB::table('tagore_cbt_exam_questions')->where('id', $data['exam_question_id'])->where('exam_id', $attempt->exam_id)->exists(), 404);
            $answer = $data['answer'] ?? null;
            if (is_array($answer) && count($answer) > 20) throw ValidationException::withMessages(['answer' => 'Too many selected values.']);
            DB::table('tagore_cbt_responses')->updateOrInsert(
                ['attempt_id' => $attemptId, 'exam_question_id' => $data['exam_question_id']],
                ['answer_json' => $answer === null ? null : json_encode($answer, JSON_THROW_ON_ERROR),
                    'marked_for_review' => (bool) ($data['marked_for_review'] ?? false),
                    'time_spent_seconds' => (int) ($data['time_spent_seconds'] ?? 0), 'updated_at' => now(), 'created_at' => now()]
            );
            return $attempt;
        });
        return response()->json(['saved' => true, 'expires_at' => $attempt->expires_at]);
    }

    public function submit(Request $request, int $attemptId, CbtScoringService $scorer)
    {
        [$userId, $roles] = $this->context($request);
        $this->assertCandidate($roles);
        $result = DB::transaction(function () use ($attemptId, $userId, $scorer) {
            $attempt = DB::table('tagore_cbt_attempts')->where('id', $attemptId)->where('student_id', $userId)->lockForUpdate()->first();
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
            return $scored;
        });
        return response()->json(['submitted' => true, 'result' => $result]);
    }

    public function result(Request $request, int $attemptId)
    {
        [$userId, $roles] = $this->context($request);
        $this->assertCandidate($roles);
        $attempt = $this->ownedAttempt($attemptId, $userId);
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
