<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\CoachingCbtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoachingCbtController extends Controller
{
    private const MANAGERS = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER'];

    public function index(Request $request)
    {
        [$roles, $institutionIds] = $this->context($request);
        $isStudent = $roles->contains('STUDENT') || (int) $request->user()->usergroup_id === 6;
        abort_unless($isStudent || $roles->intersect(self::MANAGERS)->isNotEmpty(), 403);

        $institutions = DB::table('tagore_institutions')->whereIn('id', $institutionIds)->where('status', 'active')->orderBy('display_name')->get(['id', 'display_name']);
        $questions = $isStudent ? collect() : DB::table('tagore_cbt_questions')->whereIn('institution_id', $institutionIds)->whereNull('deleted_at')->orderByDesc('id')->limit(100)->get(['id', 'institution_id', 'question_code', 'subject', 'chapter', 'topic', 'difficulty', 'question_type', 'status', 'version']);
        $papers = $isStudent
            ? $this->availablePapersForStudent($request)
            : DB::table('tagore_cbt_papers')->whereIn('institution_id', $institutionIds)->orderByDesc('id')->limit(100)->get();
        $sections = $isStudent ? collect() : DB::table('tagore_cbt_sections as s')->join('tagore_cbt_papers as p', 'p.id', '=', 's.paper_id')->whereIn('p.institution_id', $institutionIds)->orderBy('s.sort_order')->get(['s.*']);
        $approvedQuestions = $isStudent ? collect() : DB::table('tagore_cbt_questions')->whereIn('institution_id', $institutionIds)->where('status', 'approved')->whereNull('deleted_at')->orderBy('subject')->orderBy('chapter')->get(['id', 'institution_id', 'question_code', 'subject', 'chapter', 'difficulty']);
        return view('tagore.coaching.cbt.index', compact('roles', 'institutions', 'questions', 'papers', 'sections', 'approvedQuestions', 'isStudent'));
    }

    public function storeQuestion(Request $request, CoachingCbtService $service)
    {
        [$roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGERS)->isNotEmpty(), 403);
        $data = $request->validate([
            'institution_id' => 'required|integer',
            'question_code' => 'nullable|string|max:80',
            'subject' => 'required|in:PHYSICS,CHEMISTRY,MATHS,BIOLOGY',
            'chapter' => 'required|string|max:120',
            'topic' => 'nullable|string|max:120',
            'difficulty' => 'required|in:easy,medium,hard',
            'question_type' => 'required|in:single_choice,multiple_choice,numerical,assertion_reason',
            'stem' => 'required|string|max:20000',
            'options' => 'nullable|array|min:2|max:10',
            'answer' => 'required',
            'explanation' => 'nullable|string|max:12000',
            'default_marks' => 'nullable|numeric|min:0|max:100',
            'default_negative_marks' => 'nullable|numeric|min:0|max:100',
        ]);
        abort_unless(in_array((int) $data['institution_id'], $institutionIds, true), 403);
        if (in_array($data['question_type'], ['single_choice', 'multiple_choice', 'assertion_reason'], true)) {
            abort_unless(is_array($data['options']) && count($data['options']) >= 2, 422, 'Choice questions require at least two options.');
        }
        if ($data['question_type'] === 'multiple_choice') {
            abort_unless(is_array($data['answer']) && count($data['answer']) > 0, 422, 'Multiple-choice answers must be a non-empty array.');
        } elseif ($data['question_type'] === 'numerical') {
            $value = is_array($data['answer']) ? ($data['answer']['value'] ?? null) : $data['answer'];
            abort_unless(is_numeric($value), 422, 'Numerical questions require a numeric answer.');
        } else {
            abort_unless(is_string($data['answer']) && $data['answer'] !== '', 422, 'Select one correct option.');
        }

        $id = $service->createQuestion((int) $data['institution_id'], (int) $request->user()->id, $data);
        return back()->with('success', 'Question saved as draft. Approve it before adding it to a paper.');
    }

    public function approveQuestion(Request $request, int $questionId, CoachingCbtService $service)
    {
        [$roles] = $this->context($request);
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR'])->isNotEmpty(), 403);
        $question = DB::table('tagore_cbt_questions')->where('id', $questionId)->first();
        abort_unless($question && $this->canAccessInstitution($request, (int) $question->institution_id), 404);
        $service->approveQuestion($questionId);
        return back()->with('success', 'Question approved and available to paper builders.');
    }

    public function storePaper(Request $request, CoachingCbtService $service)
    {
        [$roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR'])->isNotEmpty(), 403);
        $data = $request->validate([
            'institution_id' => 'required|integer',
            'name' => 'required|string|max:160',
            'exam_type' => 'required|in:JEE_MAIN,JEE_ADVANCED,NEET',
            'duration_minutes' => 'required|integer|min:1|max:600',
            'max_attempts' => 'nullable|integer|min:1|max:5',
            'instructions' => 'nullable|string|max:5000',
            'shuffle_questions' => 'nullable|boolean',
            'shuffle_options' => 'nullable|boolean',
            'available_from' => 'nullable|date',
            'available_until' => 'nullable|date|after:available_from',
            'sections' => 'required|array|min:1|max:10',
            'sections.*.section_key' => 'required|string|max:24',
            'sections.*.title' => 'required|string|max:100',
            'sections.*.question_limit' => 'nullable|integer|min:1|max:200',
            'sections.*.required_answer_count' => 'nullable|integer|min:1|max:200',
            'sections.*.section_type' => 'nullable|in:single_choice,multiple_choice,numerical,mixed',
        ]);
        abort_unless(in_array((int) $data['institution_id'], $institutionIds, true), 403);
        $id = $service->createPaper((int) $data['institution_id'], (int) $request->user()->id, $data);
        return redirect()->route('tagore.cbt.index')->with('success', 'Mock paper created. Add approved questions and publish it.');
    }

    public function addQuestion(Request $request, int $paperId, CoachingCbtService $service)
    {
        [$roles] = $this->context($request);
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR'])->isNotEmpty(), 403);
        $paper = DB::table('tagore_cbt_papers')->where('id', $paperId)->first();
        abort_unless($paper && $this->canAccessInstitution($request, (int) $paper->institution_id), 404);
        $data = $request->validate([
            'section_id' => 'required|integer',
            'question_id' => 'required|integer',
            'marks' => 'nullable|numeric|min:0|max:100',
            'negative_marks' => 'nullable|numeric|min:0|max:100',
        ]);
        $service->addQuestionToPaper($paperId, (int) $data['section_id'], (int) $data['question_id'], isset($data['marks']) ? (float) $data['marks'] : null, isset($data['negative_marks']) ? (float) $data['negative_marks'] : null);
        return back()->with('success', 'Question added to the paper.');
    }

    public function publishPaper(Request $request, int $paperId, CoachingCbtService $service)
    {
        [$roles] = $this->context($request);
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR'])->isNotEmpty(), 403);
        $paper = DB::table('tagore_cbt_papers')->where('id', $paperId)->first();
        abort_unless($paper && $this->canAccessInstitution($request, (int) $paper->institution_id), 404);
        $service->publishPaper($paperId, (int) $request->user()->id);
        return back()->with('success', 'Mock paper published.');
    }

    public function start(Request $request, int $paperId, CoachingCbtService $service)
    {
        abort_unless((int) $request->user()->usergroup_id === 6, 403);
        $attemptId = $service->startAttempt($paperId, (int) $request->user()->id);
        return redirect()->route('tagore.cbt.player', $attemptId);
    }

    public function player(Request $request, int $attemptId, CoachingCbtService $service)
    {
        abort_unless((int) $request->user()->usergroup_id === 6, 403);
        $payload = $service->playerPayload($attemptId, (int) $request->user()->id);
        abort_unless($payload['attempt']->status === 'in_progress', 409, 'This attempt has already been submitted.');
        return view('tagore.coaching.cbt.player', $payload);
    }

    public function answer(Request $request, int $attemptId, CoachingCbtService $service)
    {
        abort_unless((int) $request->user()->usergroup_id === 6, 403);
        $data = $request->validate([
            'question_id' => 'required|integer',
            'answer' => 'nullable',
            'marked_for_review' => 'nullable|boolean',
            'time_spent_seconds' => 'nullable|integer|min:0|max:36000',
        ]);
        $service->saveAnswer($attemptId, (int) $request->user()->id, (int) $data['question_id'], $data['answer'] ?? null, (bool) ($data['marked_for_review'] ?? false), (int) ($data['time_spent_seconds'] ?? 0));
        return response()->json(['saved' => true]);
    }

    public function submit(Request $request, int $attemptId, CoachingCbtService $service)
    {
        abort_unless((int) $request->user()->usergroup_id === 6, 403);
        // Authorize ownership before scoring: otherwise a student could force-submit
        // another student's attempt by guessing or obtaining its numeric ID.
        $ownedAttempt = DB::table('tagore_cbt_attempts')
            ->where('id', $attemptId)
            ->where('student_id', $request->user()->id)
            ->first(['id']);
        abort_unless($ownedAttempt, 404);
        $service->scoreAttempt($attemptId);
        return redirect()->route('tagore.cbt.result', $attemptId);
    }

    public function result(Request $request, int $attemptId)
    {
        [$roles] = $this->context($request);
        $attempt = DB::table('tagore_cbt_attempts as a')->join('tagore_cbt_papers as p', 'p.id', '=', 'a.paper_id')
            ->where('a.id', $attemptId)->first(['a.*', 'p.name as paper_name', 'p.exam_type']);
        abort_unless($attempt, 404);
        $isStudent = (int) $attempt->student_id === (int) $request->user()->id && (int) $request->user()->usergroup_id === 6;
        $isManager = $roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR'])->isNotEmpty() && $this->canAccessInstitution($request, (int) $attempt->institution_id);
        abort_unless($isStudent || $isManager, 403);
        return view('tagore.coaching.cbt.result', compact('attempt'));
    }

    public function analytics(Request $request, int $paperId)
    {
        [$roles] = $this->context($request);
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR'])->isNotEmpty(), 403);
        $paper = DB::table('tagore_cbt_papers')->where('id', $paperId)->first();
        abort_unless($paper && $this->canAccessInstitution($request, (int) $paper->institution_id), 404);
        $attempts = DB::table('tagore_cbt_attempts as a')->join('users as u', 'u.id', '=', 'a.student_id')
            ->where('a.paper_id', $paperId)->whereIn('a.status', ['submitted', 'expired'])
            ->orderBy('a.rank')->get(['a.student_id', 'u.name', 'a.score', 'a.max_score', 'a.correct_count', 'a.incorrect_count', 'a.unanswered_count', 'a.rank', 'a.percentile', 'a.submitted_at']);
        return view('tagore.coaching.cbt.analytics', compact('paper', 'attempts'));
    }

    private function availablePapersForStudent(Request $request)
    {
        $institutionId = DB::table('tagore_institutions')->where('school_id', $request->user()->school_id)->where('status', 'active')->value('id');
        if (!$institutionId) {
            return collect();
        }
        return DB::table('tagore_cbt_papers')->where('institution_id', $institutionId)->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->orderByDesc('published_at')->get();
    }

    private function context(Request $request): array
    {
        $roles = DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $request->user()->id)->where('ur.status', 'active')->pluck('r.code')->unique()->values();
        $ids = $roles->contains('OWNER')
            ? DB::table('tagore_institutions')->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : DB::table('tagore_user_roles')->where('user_id', $request->user()->id)->where('status', 'active')->whereNotNull('institution_id')->pluck('institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        return [$roles, $ids];
    }

    private function canAccessInstitution(Request $request, int $institutionId): bool
    {
        [, $ids] = $this->context($request);
        return in_array($institutionId, $ids, true);
    }
}
