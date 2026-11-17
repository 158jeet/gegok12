<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\CbtEngineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CbtController extends Controller
{
    private const MANAGERS = ['OWNER', 'PRINCIPAL', 'COORDINATOR'];
    private const STAFF = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER'];

    private function context(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $roles = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')
            ->pluck('r.code')->unique()->values();
        $institutionIds = $roles->contains('OWNER')
            ? DB::table('tagore_institutions')->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')
                ->whereNotNull('institution_id')->pluck('institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        abort_unless($institutionIds !== [], 403);
        return [$userId, $roles, $institutionIds];
    }

    public function index(Request $request)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(array_merge(self::MANAGERS, self::STAFF, ['STUDENT']))->isNotEmpty(), 403);
        $manager = $roles->intersect(self::MANAGERS)->isNotEmpty();
        $tests = DB::table('tagore_cbt_mock_tests')->whereIn('institution_id', $institutionIds)
            ->where(function ($q) use ($manager) { if (!$manager) $q->where('status', 'published'); })
            ->orderByDesc('created_at')->limit(100)->get();
        $questions = $manager ? DB::table('tagore_cbt_questions')->whereIn('institution_id', $institutionIds)
            ->orderByDesc('created_at')->limit(100)->get(['id','exam_track','subject','chapter','topic','difficulty','question_type','status','positive_marks','negative_marks','created_at']) : collect();
        return response()->json(['manager' => $manager, 'tests' => $tests, 'question_bank' => $questions]);
    }

    public function storeQuestion(Request $request, CbtEngineService $service)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGERS)->isNotEmpty(), 403);
        $data = $request->validate([
            'institution_id' => ['required','integer'],
            'exam_track' => ['required','in:jee_main,jee_advanced,neet,foundation'],
            'subject' => ['required','in:physics,chemistry,mathematics,biology'],
            'chapter' => ['nullable','string','max:120'], 'topic' => ['nullable','string','max:120'],
            'difficulty' => ['required','in:easy,medium,hard'],
            'question_type' => ['required','in:single_choice,multiple_choice,numeric'],
            'question_text' => ['required','string','max:30000'],
            'options' => ['required_unless:question_type,numeric','array','min:2','max:8'],
            'options.*' => ['required','string','max:2000'],
            'correct_answer' => ['required'],
            'explanation' => ['nullable','string','max:30000'],
            'positive_marks' => ['required','numeric','min:0.25','max:100'],
            'negative_marks' => ['required','numeric','min:0','max:100'],
        ]);
        $institutionId = (int) $data['institution_id'];
        abort_unless(in_array($institutionId, $institutionIds, true), 403);
        if ($data['question_type'] === 'single_choice') {
            abort_unless(is_string($data['correct_answer']) && array_key_exists(strtoupper($data['correct_answer']), $data['options']), 422, 'Correct answer must match an option key.');
            $data['correct_answer'] = strtoupper($data['correct_answer']);
        } elseif ($data['question_type'] === 'multiple_choice') {
            abort_unless(is_array($data['correct_answer']) && $data['correct_answer'] !== [], 422, 'Provide one or more correct option keys.');
            $keys = array_values(array_unique(array_map(fn ($v) => strtoupper((string) $v), $data['correct_answer'])));
            abort_unless(count(array_diff($keys, array_keys($data['options']))) === 0, 422, 'Correct answers must match option keys.');
            sort($keys); $data['correct_answer'] = $keys;
        } else {
            abort_unless(is_numeric($data['correct_answer']) || (is_array($data['correct_answer']) && isset($data['correct_answer']['value']) && is_numeric($data['correct_answer']['value'])), 422, 'Numeric questions need a numeric answer.');
        }
        $id = $service->createQuestion($institutionId, $userId, $data);
        return response()->json(['question_id' => $id, 'status' => 'pending_review'], 201);
    }

    public function approveQuestion(Request $request, int $questionId, CbtEngineService $service)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGERS)->isNotEmpty(), 403);
        $institutionId = (int) DB::table('tagore_cbt_questions')->where('id', $questionId)->value('institution_id');
        abort_unless(in_array($institutionId, $institutionIds, true), 404);
        $service->approveQuestion($questionId, $institutionId, $userId);
        return response()->json(['question_id' => $questionId, 'status' => 'approved']);
    }

    public function storeTest(Request $request, CbtEngineService $service)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGERS)->isNotEmpty(), 403);
        $data = $request->validate([
            'institution_id' => ['required','integer'], 'code' => ['required','string','max:50','regex:/^[A-Za-z0-9_-]+$/'],
            'title' => ['required','string','max:180'], 'exam_track' => ['required','in:jee_main,jee_advanced,neet,foundation'],
            'mode' => ['nullable','in:full_mock,sectional,chapter_test'], 'duration_minutes' => ['required','integer','min:5','max:360'],
            'attempt_limit' => ['nullable','integer','min:1','max:10'], 'shuffle_questions' => ['nullable','boolean'],
            'shuffle_options' => ['nullable','boolean'], 'allow_calculator' => ['nullable','boolean'],
            'available_from' => ['nullable','date'], 'available_until' => ['nullable','date','after:available_from'],
            'instructions' => ['nullable','array'],
        ]);
        $institutionId = (int) $data['institution_id'];
        abort_unless(in_array($institutionId, $institutionIds, true), 403);
        abort_if(DB::table('tagore_cbt_mock_tests')->where('institution_id', $institutionId)->where('code', $data['code'])->exists(), 422, 'Mock test code already exists.');
        $id = $service->createMockTest($institutionId, $userId, $data);
        return response()->json(['mock_test_id' => $id, 'status' => 'draft'], 201);
    }

    public function storeSection(Request $request, int $testId, CbtEngineService $service)
    {
        [, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGERS)->isNotEmpty(), 403);
        $test = DB::table('tagore_cbt_mock_tests')->where('id', $testId)->whereIn('institution_id', $institutionIds)->first();
        abort_unless($test, 404);
        abort_unless($test->status === 'draft', 409);
        $data = $request->validate(['name' => ['required','string','max:100'], 'subject' => ['required','in:physics,chemistry,mathematics,biology'], 'sort_order' => ['nullable','integer','min:0','max:100']]);
        abort_unless($test->exam_track !== 'neet' || $data['subject'] !== 'mathematics', 422, 'NEET mock tests do not include Mathematics.');
        abort_unless($test->exam_track === 'neet' || $data['subject'] !== 'biology', 422, 'JEE mock tests do not include Biology.');
        $id = $service->addSection($testId, $data['name'], $data['subject'], (int) ($data['sort_order'] ?? 0));
        return response()->json(['section_id' => $id], 201);
    }

    public function attachQuestions(Request $request, int $sectionId, CbtEngineService $service)
    {
        [, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGERS)->isNotEmpty(), 403);
        $section = DB::table('tagore_cbt_test_sections as s')->join('tagore_cbt_mock_tests as t','t.id','=','s.mock_test_id')
            ->where('s.id',$sectionId)->whereIn('t.institution_id',$institutionIds)->select('t.institution_id')->first();
        abort_unless($section, 404);
        $data = $request->validate(['questions' => ['required','array','min:1','max:200'], 'questions.*.question_id' => ['required','integer'], 'questions.*.positive_marks' => ['nullable','numeric','min:0.25','max:100'], 'questions.*.negative_marks' => ['nullable','numeric','min:0','max:100']]);
        $service->attachQuestions($sectionId, (int) $section->institution_id, $data['questions']);
        return response()->json(['attached' => count($data['questions'])]);
    }

    public function publishTest(Request $request, int $testId, CbtEngineService $service)
    {
        [, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGERS)->isNotEmpty(), 403);
        $test = DB::table('tagore_cbt_mock_tests')->where('id',$testId)->whereIn('institution_id',$institutionIds)->first();
        abort_unless($test, 404);
        $service->publishMockTest($testId, (int) $test->institution_id);
        return response()->json(['mock_test_id' => $testId, 'status' => 'published']);
    }

    public function startAttempt(Request $request, int $testId, CbtEngineService $service)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->contains('STUDENT') && $roles->intersect(self::STAFF)->isEmpty(), 403);
        $test = DB::table('tagore_cbt_mock_tests')->where('id',$testId)->whereIn('institution_id',$institutionIds)->first();
        abort_unless($test, 404);
        $attemptId = $service->startAttempt($userId, $testId, (int) $test->institution_id);
        return response()->json(['attempt_id' => $attemptId, 'questions' => $service->questionsForAttempt($userId, $attemptId)]);
    }

    public function saveAnswer(Request $request, int $attemptId, int $questionId, CbtEngineService $service)
    {
        [$userId, $roles] = $this->context($request);
        abort_unless($roles->contains('STUDENT') && $roles->intersect(self::STAFF)->isEmpty(), 403);
        $data = $request->validate(['answer' => ['nullable'], 'marked_for_review' => ['nullable','boolean']]);
        $service->saveAnswer($userId, $attemptId, $questionId, $data['answer'] ?? null, (bool) ($data['marked_for_review'] ?? false));
        return response()->json(['saved' => true]);
    }

    public function submitAttempt(Request $request, int $attemptId, CbtEngineService $service)
    {
        [$userId, $roles] = $this->context($request);
        abort_unless($roles->contains('STUDENT') && $roles->intersect(self::STAFF)->isEmpty(), 403);
        return response()->json($service->submitAttempt($userId, $attemptId));
    }

    public function attemptResult(Request $request, int $attemptId, CbtEngineService $service)
    {
        [$userId, $roles] = $this->context($request);
        abort_unless($roles->contains('STUDENT') && $roles->intersect(self::STAFF)->isEmpty(), 403);
        $attempt = $service->attemptForStudent($userId, $attemptId);
        abort_unless(in_array($attempt->status, ['submitted','expired'], true), 409, 'Submit the mock test before viewing the result.');
        return response()->json(['attempt' => $attempt]);
    }
}
