<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CbtManagementController extends Controller
{
    private function scope(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $roles = DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')->pluck('r.code')->unique();
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER'])->isNotEmpty(), 403);
        $institutions = $roles->contains('OWNER')
            ? DB::table('tagore_institutions')->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')
                ->whereNotNull('institution_id')->pluck('institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        abort_unless($institutions !== [], 403);
        return [$userId, $roles, $institutions];
    }

    private function bankInScope(int $bankId, array $institutions)
    {
        $bank = DB::table('tagore_cbt_question_banks')->where('id', $bankId)->whereIn('institution_id', $institutions)->first();
        abort_unless($bank, 404);
        return $bank;
    }

    private function examInScope(int $examId, array $institutions)
    {
        $exam = DB::table('tagore_cbt_exams')->where('id', $examId)->whereIn('institution_id', $institutions)->first();
        abort_unless($exam, 404);
        return $exam;
    }

    public function index(Request $request)
    {
        [, , $institutions] = $this->scope($request);
        return view('tagore.cbt.manage', [
            'banks' => DB::table('tagore_cbt_question_banks')->whereIn('institution_id', $institutions)->orderBy('name')->get(),
            'exams' => DB::table('tagore_cbt_exams')->whereIn('institution_id', $institutions)->orderByDesc('created_at')->limit(100)->get(),
        ]);
    }

    public function storeBank(Request $request)
    {
        [$userId, , $institutions] = $this->scope($request);
        $data = $request->validate(['institution_id' => ['required', 'integer'], 'academic_year_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:160'], 'exam_track' => ['required', 'in:JEE_MAIN,JEE_ADVANCED,NEET']]);
        abort_unless(in_array((int) $data['institution_id'], $institutions, true), 403);
        $id = DB::table('tagore_cbt_question_banks')->insertGetId($data + ['created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['created' => true, 'bank_id' => $id], 201);
    }

    private function questionData(Request $request): array
    {
        return $request->validate([
            'subject_code' => ['required', 'in:physics,chemistry,mathematics,biology'],
            'chapter' => ['nullable', 'string', 'max:160'], 'difficulty' => ['required', 'in:easy,medium,hard'],
            'question_type' => ['required', 'in:single_choice,multiple_choice,numerical'],
            'stem' => ['required', 'string', 'max:20000'], 'options' => ['nullable', 'array'],
            'answer_key' => ['required'], 'explanation' => ['nullable', 'string', 'max:20000'],
            'default_marks' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'default_negative_marks' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function normalizedQuestion(array $data): array
    {
        $options = $data['options'] ?? null;
        if ($data['question_type'] !== 'numerical' && (!is_array($options) || count($options) < 2 || count($options) > 10)) {
            throw ValidationException::withMessages(['options' => 'Choice questions require 2 to 10 options.']);
        }
        if ($data['question_type'] === 'single_choice' && (!is_string($data['answer_key']) || !array_key_exists($data['answer_key'], $options ?? []))) {
            throw ValidationException::withMessages(['answer_key' => 'Select one valid option key.']);
        }
        if ($data['question_type'] === 'multiple_choice' && (!is_array($data['answer_key']) || $data['answer_key'] === [] || array_diff($data['answer_key'], array_keys($options ?? [])) !== [])) {
            throw ValidationException::withMessages(['answer_key' => 'Select one or more valid option keys.']);
        }
        if ($data['question_type'] === 'numerical' && (!is_array($data['answer_key']) || !isset($data['answer_key']['value']) || !is_numeric($data['answer_key']['value']))) {
            throw ValidationException::withMessages(['answer_key' => 'Numerical answer_key must contain a numeric value.']);
        }
        return [
            'subject_code' => $data['subject_code'], 'chapter' => $data['chapter'] ?? null, 'difficulty' => $data['difficulty'],
            'question_type' => $data['question_type'], 'stem' => $data['stem'],
            'options_json' => $options === null ? null : json_encode($options, JSON_THROW_ON_ERROR),
            'answer_key_json' => json_encode($data['answer_key'], JSON_THROW_ON_ERROR),
            'explanation' => $data['explanation'] ?? null, 'default_marks' => $data['default_marks'],
            'default_negative_marks' => $data['default_negative_marks'], 'updated_at' => now(),
        ];
    }

    public function storeQuestion(Request $request, int $bankId)
    {
        [$userId, , $institutions] = $this->scope($request);
        $this->bankInScope($bankId, $institutions);
        $row = $this->normalizedQuestion($this->questionData($request)) + [
            'question_bank_id' => $bankId, 'created_by' => $userId, 'status' => 'approved', 'created_at' => now(),
        ];
        $id = DB::table('tagore_cbt_questions')->insertGetId($row);
        return response()->json(['created' => true, 'question_id' => $id], 201);
    }

    public function importQuestions(Request $request, int $bankId)
    {
        [$userId, , $institutions] = $this->scope($request);
        $this->bankInScope($bankId, $institutions);
        $data = $request->validate(['questions' => ['required', 'array', 'min:1', 'max:500'], 'questions.*' => ['required', 'array']]);
        $created = DB::transaction(function () use ($data, $bankId, $userId) {
            $ids = [];
            foreach ($data['questions'] as $item) {
                $validated = validator($item, [
                    'subject_code' => ['required', 'in:physics,chemistry,mathematics,biology'],
                    'chapter' => ['nullable', 'string', 'max:160'], 'difficulty' => ['required', 'in:easy,medium,hard'],
                    'question_type' => ['required', 'in:single_choice,multiple_choice,numerical'],
                    'stem' => ['required', 'string', 'max:20000'], 'options' => ['nullable', 'array'],
                    'answer_key' => ['required'], 'explanation' => ['nullable', 'string', 'max:20000'],
                    'default_marks' => ['required', 'numeric', 'min:0.01', 'max:100'],
                    'default_negative_marks' => ['required', 'numeric', 'min:0', 'max:100'],
                ])->validate();
                $ids[] = DB::table('tagore_cbt_questions')->insertGetId($this->normalizedQuestion($validated) + [
                    'question_bank_id' => $bankId, 'created_by' => $userId, 'status' => 'approved', 'created_at' => now(),
                ]);
            }
            return $ids;
        });
        return response()->json(['imported' => count($created), 'question_ids' => $created], 201);
    }

    public function updateQuestion(Request $request, int $questionId)
    {
        [$userId, , $institutions] = $this->scope($request);
        $question = DB::table('tagore_cbt_questions as q')->join('tagore_cbt_question_banks as b', 'b.id', '=', 'q.question_bank_id')
            ->where('q.id', $questionId)->whereIn('b.institution_id', $institutions)->first(['q.*']);
        abort_unless($question, 404);
        abort_if(DB::table('tagore_cbt_exam_questions as eq')->join('tagore_cbt_exams as e', 'e.id', '=', 'eq.exam_id')
            ->where('eq.question_id', $questionId)->where('e.status', 'published')->exists(), 409, 'Questions in published exams cannot be edited.');
        $row = $this->normalizedQuestion($this->questionData($request));
        DB::table('tagore_cbt_questions')->where('id', $questionId)->update($row);
        return response()->json(['updated' => true, 'question_id' => $questionId]);
    }

    public function deleteQuestion(Request $request, int $questionId)
    {
        [, , $institutions] = $this->scope($request);
        $question = DB::table('tagore_cbt_questions as q')->join('tagore_cbt_question_banks as b', 'b.id', '=', 'q.question_bank_id')
            ->where('q.id', $questionId)->whereIn('b.institution_id', $institutions)->first(['q.id']);
        abort_unless($question, 404);
        abort_if(DB::table('tagore_cbt_exam_questions')->where('question_id', $questionId)->exists(), 409, 'Remove the question from draft exams before deleting it.');
        DB::table('tagore_cbt_questions')->where('id', $questionId)->delete();
        return response()->json(['deleted' => true]);
    }

    public function storeExam(Request $request)
    {
        [$userId, , $institutions] = $this->scope($request);
        $data = $request->validate(['institution_id' => ['required', 'integer'], 'academic_year_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:180'], 'exam_track' => ['required', 'in:JEE_MAIN,JEE_ADVANCED,NEET'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'], 'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'], 'shuffle_questions' => ['sometimes', 'boolean'], 'shuffle_options' => ['sometimes', 'boolean']]);
        abort_unless(in_array((int) $data['institution_id'], $institutions, true), 403);
        $id = DB::table('tagore_cbt_exams')->insertGetId($data + ['status' => 'draft', 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['created' => true, 'exam_id' => $id], 201);
    }

    public function attachQuestions(Request $request, int $examId)
    {
        [, , $institutions] = $this->scope($request);
        $exam = $this->examInScope($examId, $institutions);
        abort_unless($exam->status === 'draft', 409, 'Published exams are immutable.');
        $data = $request->validate(['questions' => ['required', 'array', 'min:1', 'max:500'],
            'questions.*.question_id' => ['required', 'integer'], 'questions.*.section_code' => ['nullable', 'string', 'max:24'],
            'questions.*.marks' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'questions.*.negative_marks' => ['required', 'numeric', 'min:0', 'max:100']]);
        DB::transaction(function () use ($data, $exam) {
            $order = (int) DB::table('tagore_cbt_exam_questions')->where('exam_id', $exam->id)->max('display_order');
            foreach ($data['questions'] as $item) {
                $valid = DB::table('tagore_cbt_questions as q')->join('tagore_cbt_question_banks as b', 'b.id', '=', 'q.question_bank_id')
                    ->where('q.id', $item['question_id'])->where('b.institution_id', $exam->institution_id)
                    ->where('b.exam_track', $exam->exam_track)->where('q.status', 'approved')->exists();
                abort_unless($valid, 422, 'Every question must be approved and belong to this institution and exam track.');
                DB::table('tagore_cbt_exam_questions')->updateOrInsert(
                    ['exam_id' => $exam->id, 'question_id' => $item['question_id']],
                    ['section_code' => $item['section_code'] ?? null, 'display_order' => ++$order,
                     'marks' => $item['marks'], 'negative_marks' => $item['negative_marks'], 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        return response()->json(['attached' => count($data['questions'])]);
    }

    public function scheduleExam(Request $request, int $examId)
    {
        [, , $institutions] = $this->scope($request);
        $exam = $this->examInScope($examId, $institutions);
        abort_unless($exam->status === 'draft', 409, 'Published exams cannot be rescheduled.');
        $data = $request->validate([
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        abort_if(($data['starts_at'] ?? null) && !($data['ends_at'] ?? null), 422, 'Set both the start and end time for a scheduled exam.');
        abort_if(!($data['starts_at'] ?? null) && ($data['ends_at'] ?? null), 422, 'Set both the start and end time for a scheduled exam.');
        DB::table('tagore_cbt_exams')->where('id', $examId)->update([
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'updated_at' => now(),
        ]);
        return response()->json(['scheduled' => true, 'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null]);
    }

    public function exportAnalytics(Request $request, int $examId)
    {
        [, , $institutions] = $this->scope($request);
        $this->examInScope($examId, $institutions);
        $rows = DB::table('tagore_cbt_attempts as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.student_id')
            ->where('a.exam_id', $examId)->where('a.status', 'submitted')
            ->orderByDesc('a.score')->orderBy('a.submitted_at')
            ->get(['u.name as student_name', 'a.student_id', 'a.score', 'a.max_score', 'a.accuracy',
                'a.correct_count', 'a.wrong_count', 'a.unanswered_count', 'a.submitted_at']);
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student', 'Student ID', 'Score', 'Maximum Score', 'Accuracy %', 'Correct', 'Wrong', 'Unanswered', 'Submitted At']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->student_name, $row->student_id, $row->score, $row->max_score, $row->accuracy,
                    $row->correct_count, $row->wrong_count, $row->unanswered_count, $row->submitted_at]);
            }
            fclose($out);
        }, 'cbt-exam-'.$examId.'-results.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function analytics(Request $request, int $examId)
    {
        [, , $institutions] = $this->scope($request);
        $this->examInScope($examId, $institutions);
        $attempts = DB::table('tagore_cbt_attempts as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.student_id')
            ->where('a.exam_id', $examId)->where('a.status', 'submitted')
            ->orderByDesc('a.score')->orderBy('a.submitted_at')
            ->get(['a.id', 'a.student_id', 'u.name as student_name', 'a.score', 'a.max_score', 'a.accuracy',
                'a.correct_count', 'a.wrong_count', 'a.unanswered_count', 'a.submitted_at']);
        $leaderboard = $attempts->values()->map(function ($attempt, $index) use ($attempts) {
            $attempt->rank = $index + 1;
            $attempt->percentile = $attempts->count() > 1
                ? round((($attempts->count() - $index - 1) / ($attempts->count() - 1)) * 100, 2) : 100.0;
            return $attempt;
        });
        $subjectBreakdown = DB::table('tagore_cbt_responses as r')
            ->join('tagore_cbt_attempts as a', 'a.id', '=', 'r.attempt_id')
            ->join('tagore_cbt_exam_questions as eq', 'eq.id', '=', 'r.exam_question_id')
            ->join('tagore_cbt_questions as q', 'q.id', '=', 'eq.question_id')
            ->where('a.exam_id', $examId)->where('a.status', 'submitted')
            ->groupBy('q.subject_code')
            ->orderBy('q.subject_code')
            ->get([
                'q.subject_code',
                DB::raw('COUNT(*) as response_count'),
                DB::raw('SUM(CASE WHEN r.is_correct = 1 THEN 1 ELSE 0 END) as correct_count'),
                DB::raw('ROUND(AVG(COALESCE(r.awarded_marks, 0)), 2) as average_marks'),
            ]);
        $topicBreakdown = DB::table('tagore_cbt_responses as r')
            ->join('tagore_cbt_attempts as a', 'a.id', '=', 'r.attempt_id')
            ->join('tagore_cbt_exam_questions as eq', 'eq.id', '=', 'r.exam_question_id')
            ->join('tagore_cbt_questions as q', 'q.id', '=', 'eq.question_id')
            ->where('a.exam_id', $examId)->where('a.status', 'submitted')
            ->groupBy('q.subject_code', 'q.chapter')
            ->orderBy('q.subject_code')->orderBy('q.chapter')
            ->get([
                'q.subject_code', 'q.chapter',
                DB::raw('COUNT(*) as response_count'),
                DB::raw('SUM(CASE WHEN r.is_correct = 1 THEN 1 ELSE 0 END) as correct_count'),
                DB::raw('ROUND(AVG(COALESCE(r.awarded_marks, 0)), 2) as average_marks'),
            ]);
        return response()->json([
            'attempt_count' => $attempts->count(),
            'average_score' => round((float) ($attempts->avg('score') ?? 0), 2),
            'average_accuracy' => round((float) ($attempts->avg('accuracy') ?? 0), 2),
            'leaderboard' => $leaderboard,
            'subject_breakdown' => $subjectBreakdown,
            'topic_breakdown' => $topicBreakdown,
        ]);
    }

    public function publishExam(Request $request, int $examId)
    {
        [$userId, , $institutions] = $this->scope($request);
        $exam = $this->examInScope($examId, $institutions);
        abort_unless($exam->status === 'draft', 409, 'Only draft exams can be published.');
        $count = DB::table('tagore_cbt_exam_questions')->where('exam_id', $examId)->count();
        abort_if($count === 0, 422, 'Add at least one question before publishing.');
        DB::table('tagore_cbt_exams')->where('id', $examId)->update([
            'status' => 'published', 'published_by' => $userId, 'published_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['published' => true, 'question_count' => $count]);
    }
}
