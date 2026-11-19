<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\View\View;

class OlympiadController extends Controller
{
    private const MANAGER_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR'];

    private function context(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $roles = DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')->pluck('r.code')->unique()->values();
        $institutionIds = $roles->contains('OWNER')
            ? DB::table('tagore_institutions')->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')->whereNotNull('institution_id')->pluck('institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        abort_unless($institutionIds !== [], 403);
        return [$userId, $roles, $institutionIds];
    }

    private function studentGradeForInstitution(int $userId, int $institutionId): ?string
    {
        $schoolId = DB::table('tagore_institutions')->where('id', $institutionId)->value('school_id');
        if (!$schoolId) return null;
        $record = DB::table('student_academics as sa')
            ->join('standards_link as sl', 'sl.id', '=', 'sa.standardLink_id')
            ->join('standards as s', 's.id', '=', 'sl.standard_id')
            ->where('sa.user_id', $userId)->where('sa.school_id', $schoolId)->where('sl.school_id', $schoolId)
            ->whereNull('sa.deleted_at')->whereNull('sl.deleted_at')->whereNull('s.deleted_at')
            ->where('sl.status', 1)->orderByDesc('sa.academic_year_id')->select('s.name as grade_name', 's.order as grade_order')->first();
        if (!$record) return null;
        $digits = preg_replace('/\D+/', '', (string) $record->grade_name);
        $grade = (int) $digits;
        if ($grade < 1 || $grade > 12) $grade = (int) $record->grade_order - 3;
        return $grade >= 1 && $grade <= 12 ? (string) $grade : null;
    }
    public function index(Request $request): View
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        $canManage = $roles->intersect(self::MANAGER_ROLES)->isNotEmpty();
        $isTeacher = $roles->contains('TEACHER');
        $isStudent = $roles->contains('STUDENT') && !$canManage && !$isTeacher;
        abort_unless($canManage || $isTeacher || $isStudent, 403);
        if ($isStudent) {
            $gradeMap = [];
            foreach ($institutionIds as $institutionId) {
                $grade = $this->studentGradeForInstitution($userId, (int) $institutionId);
                if ($grade !== null) $gradeMap[(int) $institutionId] = $grade;
            }
            $events = DB::table('tagore_olympiads as o')->join('tagore_institutions as i', 'i.id', '=', 'o.institution_id')
                ->where('o.status', 'published')->whereIn('o.institution_id', array_keys($gradeMap))
                ->select('o.*', 'i.display_name as institution_name')->orderBy('o.exam_starts_at')->get()
                ->filter(function ($event) use ($gradeMap) {
                    $classes = array_map('strval', json_decode($event->target_classes, true) ?: []);
                    return isset($gradeMap[(int) $event->institution_id]) && in_array($gradeMap[(int) $event->institution_id], $classes, true);
                })->values();
            $registrations = $events->isEmpty() ? collect() : DB::table('tagore_olympiad_registrations')
                ->where('student_user_id', $userId)->whereIn('olympiad_id', $events->pluck('id'))->get()->keyBy('olympiad_id');
            return view('tagore.olympiad.student-index', compact('events', 'registrations', 'gradeMap'));
        }
        $events = DB::table('tagore_olympiads as o')->join('tagore_institutions as i', 'i.id', '=', 'o.institution_id')
            ->whereIn('o.institution_id', $institutionIds)
            ->select('o.*', 'i.display_name as institution_name')
            ->selectSub(DB::table('tagore_olympiad_questions as q')->selectRaw('COUNT(*)')->whereColumn('q.olympiad_id', 'o.id'), 'question_count')
            ->selectSub(DB::table('tagore_olympiad_questions as q')->selectRaw('COUNT(*)')->whereColumn('q.olympiad_id', 'o.id')->whereNotNull('q.approved_at'), 'approved_question_count')
            ->orderByDesc('o.created_at')->get();
        $institutions = $canManage ? DB::table('tagore_institutions')->whereIn('id', $institutionIds)->where('status', 'active')->orderBy('display_name')->get(['id', 'display_name']) : collect();
        return view('tagore.olympiad.index', compact('events', 'institutions', 'canManage'));
    }

    public function store(Request $request)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGER_ROLES)->isNotEmpty(), 403);
        $data = $request->validate([
            'institution_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:180'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'description' => ['nullable', 'string', 'max:5000'], 'target_classes' => ['required', 'array', 'min:1', 'max:15'],
            'target_classes.*' => ['required', 'string', 'max:30'],
            'registration_opens_at' => ['nullable', 'date'], 'registration_closes_at' => ['nullable', 'date', 'after_or_equal:registration_opens_at', 'before:exam_starts_at'],
            'exam_starts_at' => ['required', 'date'], 'exam_ends_at' => ['required', 'date', 'after:exam_starts_at'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:240'], 'max_marks' => ['required', 'numeric', 'min:1', 'max:10000'],
        ]);
        // datetime-local inputs are school-local (India); store UTC timestamps consistently.
        foreach (['registration_opens_at','registration_closes_at','exam_starts_at','exam_ends_at'] as $field) {
            if (!empty($data[$field])) $data[$field] = Carbon::parse($data[$field], 'Asia/Kolkata')->utc()->format('Y-m-d H:i:s');
        }
        $institutionId = (int) $data['institution_id'];
        abort_unless(in_array($institutionId, $institutionIds, true), 403);
        if (DB::table('tagore_olympiads')->where('institution_id', $institutionId)->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'This Olympiad code is already used by this institution.']);
        }
        DB::table('tagore_olympiads')->insert([
            'institution_id' => $institutionId, 'title' => $data['title'], 'code' => $data['code'],
            'description' => $data['description'] ?? null, 'target_classes' => json_encode(array_values(array_unique($data['target_classes']))),
            'registration_opens_at' => $data['registration_opens_at'] ?? null, 'registration_closes_at' => $data['registration_closes_at'] ?? null,
            'exam_starts_at' => $data['exam_starts_at'] ?? null, 'exam_ends_at' => $data['exam_ends_at'] ?? null,
            'duration_minutes' => $data['duration_minutes'], 'max_marks' => $data['max_marks'], 'status' => 'draft',
            'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('tagore.olympiad.index')->with('status', 'Olympiad saved as draft. Add and review questions before publishing.');
    }

    public function show(Request $request, int $olympiadId): View
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        $canManage = $roles->intersect(self::MANAGER_ROLES)->isNotEmpty();
        abort_unless($canManage || $roles->contains('TEACHER'), 403);
        $event = DB::table('tagore_olympiads')->where('id', $olympiadId)->whereIn('institution_id', $institutionIds)->first();
        abort_unless($event, 404);
        $questions = DB::table('tagore_olympiad_questions')->where('olympiad_id', $event->id)->orderBy('sort_order')->orderBy('id')->get();
        $questions = $questions->map(function ($q) { $q->options = json_decode($q->options, true) ?: []; $q->metadata = json_decode($q->metadata ?? '[]', true) ?: []; return $q; });
        $registrations = $canManage ? DB::table('tagore_olympiad_registrations as r')->join('users as u', 'u.id', '=', 'r.student_user_id')->where('r.olympiad_id', $event->id)->select('r.*', 'u.name as student_name')->orderBy('r.created_at')->get() : collect();
        return view('tagore.olympiad.show', compact('event', 'questions', 'canManage', 'registrations'));
    }

    public function importQuestions(Request $request, int $olympiadId)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(array_merge(self::MANAGER_ROLES, ['TEACHER']))->isNotEmpty(), 403);
        $event = DB::table('tagore_olympiads')->where('id', $olympiadId)->whereIn('institution_id', $institutionIds)->first();
        abort_unless($event, 404);
        abort_unless($event->status === 'draft', 409);

        $request->validate([
            'questions_json' => ['nullable', 'string', 'max:1000000'],
            'question_bank_file' => ['nullable', 'file', 'mimes:xlsx,xls,csv,json', 'max:10240'],
        ]);
        $file = $request->file('question_bank_file');
        $source = 'json_import';
        if ($file) {
            $extension = strtolower($file->getClientOriginalExtension());
            if ($extension === 'json') {
                try { $items = json_decode(file_get_contents($file->getRealPath()), true, 512, JSON_THROW_ON_ERROR); }
                catch (\JsonException $e) { throw ValidationException::withMessages(['question_bank_file' => 'The uploaded JSON file is invalid.']); }
            } else {
                try {
                    $spreadsheet = IOFactory::load($file->getRealPath());
                    $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
                    $spreadsheet->disconnectWorksheets();
                } catch (\Throwable $e) {
                    throw ValidationException::withMessages(['question_bank_file' => 'Could not read this spreadsheet. Upload an Exameow-compatible XLSX/CSV question template.']);
                }
                $header = reset($rows) ?: [];
                if (stripos((string) ($header['A'] ?? ''), 'type') === false || stripos((string) ($header['B'] ?? ''), 'question') === false || stripos((string) ($header['K'] ?? ''), 'answer') === false) {
                    throw ValidationException::withMessages(['question_bank_file' => 'Use the Tagore/Exameow question template with Type, Question, Option A-H, Answer, Analysis, Subject, Chapter and Difficulty columns.']);
                }
                $items = [];
                foreach (array_slice($rows, 1) as $row) {
                    $questionText = trim((string) ($row['B'] ?? ''));
                    if ($questionText === '') continue;
                    $type = trim((string) ($row['A'] ?? ''));
                    if ($type !== '' && stripos($type, 'single choice') === false) {
                        throw ValidationException::withMessages(['question_bank_file' => 'Only Single Choice questions are supported in this import. Convert other question types before importing.']);
                    }
                    $options = [];
                    foreach (['A','B','C','D','E','F','G','H'] as $index => $optionKey) {
                        $column = chr(ord('C') + $index);
                        $optionText = trim((string) ($row[$column] ?? ''));
                        if ($optionText !== '') $options[$optionKey] = $optionText;
                    }
                    $metadata = array_filter([
                        'analysis' => trim((string) ($row['L'] ?? '')),
                        'subject' => trim((string) ($row['M'] ?? '')),
                        'chapter' => trim((string) ($row['N'] ?? '')),
                        'difficulty' => trim((string) ($row['O'] ?? '')),
                    ], fn ($value) => $value !== '');
                    $items[] = [
                        'question' => $questionText, 'options' => $options,
                        'answer' => strtoupper(trim((string) ($row['K'] ?? ''))),
                        'marks' => 1, 'metadata' => $metadata,
                    ];
                }
                $source = $extension === 'xlsx' || $extension === 'xls' ? 'exameow_xlsx' : 'spreadsheet_import';
            }
        } else {
            $json = trim((string) $request->input('questions_json', ''));
            if ($json === '') throw ValidationException::withMessages(['questions_json' => 'Upload an Excel/CSV question bank or paste question bank JSON.']);
            try { $items = json_decode($json, true, 512, JSON_THROW_ON_ERROR); }
            catch (\JsonException $e) { throw ValidationException::withMessages(['questions_json' => 'The question bank must be valid JSON.']); }
        }
        if (!is_array($items) || count($items) < 1 || count($items) > 200) {
            throw ValidationException::withMessages(['questions_json' => 'Import between 1 and 200 questions at a time.']);
        }
        $validator = Validator::make(['questions' => $items], [
            'questions' => ['required', 'array'], 'questions.*.question' => ['required', 'string', 'max:10000'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:8'], 'questions.*.options.*' => ['required', 'string', 'max:1000'],
            'questions.*.answer' => ['required', 'string', 'max:20'], 'questions.*.marks' => ['nullable', 'numeric', 'min:0.25', 'max:100'],
            'questions.*.metadata' => ['nullable', 'array'],
        ]);
        if ($validator->fails()) throw new ValidationException($validator);
        foreach ($items as $item) {
            if (!array_key_exists(strtoupper($item['answer']), $item['options'])) {
                throw ValidationException::withMessages(['questions_json' => 'Each answer must match an option key (for example A, B, C, or D).']);
            }
        }
        DB::transaction(function () use ($items, $event, $source) {
            $order = (int) DB::table('tagore_olympiad_questions')->where('olympiad_id', $event->id)->max('sort_order');
            foreach ($items as $item) {
                DB::table('tagore_olympiad_questions')->insert([
                    'olympiad_id' => $event->id, 'question_text' => $item['question'], 'options' => json_encode($item['options']),
                    'correct_option' => strtoupper($item['answer']), 'marks' => $item['marks'] ?? 1, 'sort_order' => ++$order,
                    'source' => $source, 'metadata' => isset($item['metadata']) ? json_encode($item['metadata']) : null,
                    'approved_by' => null, 'approved_at' => null, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        return redirect()->route('tagore.olympiad.show', $event->id)->with('status', count($items).' questions imported as pending review. They are not publishable until approved.');
    }

    public function downloadQuestionTemplate(Request $request)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(array_merge(self::MANAGER_ROLES, ['TEACHER']))->isNotEmpty(), 403);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Question Bank');
        $sheet->fromArray([['Type (required)', 'Question (required)', 'Option A', 'Option B', 'Option C', 'Option D', 'Option E', 'Option F', 'Option G', 'Option H', 'Answer', 'Analysis', 'Subject', 'Chapter', 'Difficulty']], null, 'A1');
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);
        foreach (range('A', 'O') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'tagore-olympiad-question-template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
    public function register(Request $request, int $olympiadId)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->contains('STUDENT') && $roles->intersect(array_merge(self::MANAGER_ROLES, ['TEACHER']))->isEmpty(), 403);
        $event = DB::table('tagore_olympiads')->where('id', $olympiadId)->where('status', 'published')->whereIn('institution_id', $institutionIds)->first();
        abort_unless($event, 404);
        $grade = $this->studentGradeForInstitution($userId, (int) $event->institution_id);
        $classes = array_map('strval', json_decode($event->target_classes, true) ?: []);
        abort_unless($grade !== null && in_array($grade, $classes, true), 403);
        $now = now();
        if ($event->registration_opens_at && $now->lt($event->registration_opens_at)) throw ValidationException::withMessages(['registration' => 'Registration has not opened yet.']);
        if ($event->registration_closes_at && $now->gt($event->registration_closes_at)) throw ValidationException::withMessages(['registration' => 'The registration deadline has passed.']);
        if ($event->exam_starts_at && $now->gte($event->exam_starts_at)) throw ValidationException::withMessages(['registration' => 'Registration is closed because the exam has started.']);
        $existing = DB::table('tagore_olympiad_registrations')->where('olympiad_id', $event->id)->where('student_user_id', $userId)->first();
        if ($existing) return redirect()->route('tagore.olympiad.index')->with('status', 'You are already registered for '.$event->title.'.');
        DB::table('tagore_olympiad_registrations')->insert([
            'olympiad_id' => $event->id, 'institution_id' => $event->institution_id, 'student_user_id' => $userId,
            'registration_no' => strtoupper($event->code.'-'.Str::random(8)), 'status' => 'registered', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('tagore.olympiad.index')->with('status', 'Registration confirmed for '.$event->title.'.');
    }

    public function exam(Request $request, int $olympiadId)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->contains('STUDENT') && $roles->intersect(array_merge(self::MANAGER_ROLES, ['TEACHER']))->isEmpty(), 403);
        $event = DB::table('tagore_olympiads')->where('id', $olympiadId)->where('status', 'published')->whereIn('institution_id', $institutionIds)->first();
        abort_unless($event, 404);
        $grade = $this->studentGradeForInstitution($userId, (int) $event->institution_id);
        abort_unless($grade !== null && in_array($grade, array_map('strval', json_decode($event->target_classes, true) ?: []), true), 403);
        $registration = DB::table('tagore_olympiad_registrations')->where('olympiad_id', $event->id)->where('student_user_id', $userId)->first();
        abort_unless($registration, 403);
        if (in_array($registration->status, ['submitted', 'submitted_late'], true)) return redirect()->route('tagore.olympiad.result', $event->id);
        if (!$event->exam_starts_at || !$event->exam_ends_at) throw ValidationException::withMessages(['exam' => 'The exam schedule has not been configured.']);
        $now = now();
        if ($now->lt($event->exam_starts_at)) return redirect()->route('tagore.olympiad.index')->with('status', 'The exam has not started yet.');
        if ($now->gt($event->exam_ends_at)) return redirect()->route('tagore.olympiad.index')->with('status', 'The exam window has closed. Contact the school if you need assistance.');
        if ($registration->status === 'registered') {
            DB::table('tagore_olympiad_registrations')->where('id', $registration->id)->where('status', 'registered')->update(['status' => 'in_progress', 'started_at' => now(), 'updated_at' => now()]);
            $registration = DB::table('tagore_olympiad_registrations')->where('id', $registration->id)->first();
        }
        $questions = DB::table('tagore_olympiad_questions')->where('olympiad_id', $event->id)->whereNotNull('approved_at')->orderBy('sort_order')->orderBy('id')->get(['id', 'question_text', 'options', 'marks']);
        abort_unless($questions->isNotEmpty(), 409);
        $questions = $questions->map(function ($q) { $q->options = json_decode($q->options, true) ?: []; return $q; });
        $deadline = min(strtotime($event->exam_ends_at), strtotime($registration->started_at) + ((int) $event->duration_minutes * 60));
        $remainingSeconds = max(0, $deadline - time());
        $savedAnswers = json_decode($registration->answers ?? '{}', true) ?: [];
        return view('tagore.olympiad.student-exam', compact('event', 'questions', 'registration', 'remainingSeconds', 'savedAnswers'));
    }

    public function saveAnswers(Request $request, int $olympiadId)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->contains('STUDENT') && $roles->intersect(array_merge(self::MANAGER_ROLES, ['TEACHER']))->isEmpty(), 403);
        $event = DB::table('tagore_olympiads')->where('id', $olympiadId)->where('status', 'published')->whereIn('institution_id', $institutionIds)->first();
        abort_unless($event, 404);
        $registration = DB::table('tagore_olympiad_registrations')->where('olympiad_id', $event->id)->where('student_user_id', $userId)->first();
        abort_unless($registration && $registration->status === 'in_progress' && $registration->started_at, 409);
        $deadline = min(strtotime($event->exam_ends_at), strtotime($registration->started_at) + ((int) $event->duration_minutes * 60));
        abort_unless(time() <= $deadline, 409);

        $data = $request->validate(['answers' => ['nullable', 'array', 'max:200']]);
        $questions = DB::table('tagore_olympiad_questions')->where('olympiad_id', $event->id)->whereNotNull('approved_at')
            ->get(['id', 'options'])->keyBy('id');
        $cleanAnswers = [];
        foreach (($data['answers'] ?? []) as $questionId => $answer) {
            $question = $questions->get((int) $questionId);
            if (!$question || !is_scalar($answer)) continue;
            $option = strtoupper(trim((string) $answer));
            $options = json_decode($question->options, true) ?: [];
            if (array_key_exists($option, $options)) $cleanAnswers[(string) $question->id] = $option;
        }
        $updated = DB::table('tagore_olympiad_registrations')->where('id', $registration->id)->where('status', 'in_progress')
            ->update(['answers' => json_encode($cleanAnswers), 'updated_at' => now()]);
        abort_unless($updated === 1, 409);
        return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
    }

    public function submitExam(Request $request, int $olympiadId)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->contains('STUDENT') && $roles->intersect(array_merge(self::MANAGER_ROLES, ['TEACHER']))->isEmpty(), 403);
        $event = DB::table('tagore_olympiads')->where('id', $olympiadId)->where('status', 'published')->whereIn('institution_id', $institutionIds)->first();
        abort_unless($event, 404);
        $registration = DB::table('tagore_olympiad_registrations')->where('olympiad_id', $event->id)->where('student_user_id', $userId)->first();
        abort_unless($registration, 403);
        if (in_array($registration->status, ['submitted', 'submitted_late'], true)) return redirect()->route('tagore.olympiad.result', $event->id);
        abort_unless($registration->status === 'in_progress' && $registration->started_at, 409);
        $submittedAnswers = (array) $request->input('answers', []);
        $questions = DB::table('tagore_olympiad_questions')->where('olympiad_id', $event->id)->whereNotNull('approved_at')->orderBy('sort_order')->get(['id', 'options', 'correct_option', 'marks']);
        $score = 0.0; $totalMarks = 0.0; $cleanAnswers = [];
        foreach ($questions as $question) {
            $totalMarks += (float) $question->marks;
            $options = json_decode($question->options, true) ?: [];
            $chosen = strtoupper(trim((string) ($submittedAnswers[$question->id] ?? '')));
            if ($chosen !== '' && array_key_exists($chosen, $options)) {
                $cleanAnswers[(string) $question->id] = $chosen;
                if ($chosen === strtoupper($question->correct_option)) $score += (float) $question->marks;
            }
        }
        $deadline = min(strtotime($event->exam_ends_at), strtotime($registration->started_at) + ((int) $event->duration_minutes * 60));
        $status = time() > $deadline ? 'submitted_late' : 'submitted';
        DB::transaction(function () use ($registration, $cleanAnswers, $score, $totalMarks, $status) {
            $locked = DB::table('tagore_olympiad_registrations')->where('id', $registration->id)->lockForUpdate()->first();
            if (!$locked || in_array($locked->status, ['submitted', 'submitted_late'], true)) return;
            DB::table('tagore_olympiad_registrations')->where('id', $registration->id)->update([
                'answers' => json_encode($cleanAnswers), 'score' => round($score, 2), 'total_marks' => round($totalMarks, 2),
                'status' => $status, 'submitted_at' => now(), 'updated_at' => now(),
            ]);
        });
        return redirect()->route('tagore.olympiad.result', $event->id);
    }

    public function result(Request $request, int $olympiadId)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->contains('STUDENT') && $roles->intersect(array_merge(self::MANAGER_ROLES, ['TEACHER']))->isEmpty(), 403);
        $event = DB::table('tagore_olympiads')->where('id', $olympiadId)->where('status', 'published')->whereIn('institution_id', $institutionIds)->first();
        abort_unless($event, 404);
        $registration = DB::table('tagore_olympiad_registrations')->where('olympiad_id', $event->id)->where('student_user_id', $userId)->first();
        abort_unless($registration, 403);
        if (!in_array($registration->status, ['submitted', 'submitted_late'], true)) return redirect()->route('tagore.olympiad.exam', $event->id);
        return view('tagore.olympiad.student-result', compact('event', 'registration'));
    }
    public function approveQuestion(Request $request, int $questionId)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGER_ROLES)->isNotEmpty(), 403);
        $question = DB::table('tagore_olympiad_questions as q')->join('tagore_olympiads as o', 'o.id', '=', 'q.olympiad_id')
            ->where('q.id', $questionId)->whereIn('o.institution_id', $institutionIds)->select('q.id', 'q.olympiad_id', 'q.approved_at')->first();
        abort_unless($question, 404);
        abort_unless(DB::table('tagore_olympiads')->where('id', $question->olympiad_id)->value('status') === 'draft', 409);
        DB::table('tagore_olympiad_questions')->where('id', $questionId)->update(['approved_by' => $userId, 'approved_at' => now(), 'updated_at' => now()]);
        return back()->with('status', 'Question approved.');
    }

    public function publish(Request $request, int $olympiadId)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGER_ROLES)->isNotEmpty(), 403);
        $event = DB::table('tagore_olympiads')->where('id', $olympiadId)->whereIn('institution_id', $institutionIds)->first();
        abort_unless($event, 404);
        abort_unless($event->status === 'draft', 409);
        if (!$event->exam_starts_at || !$event->exam_ends_at) {
            throw ValidationException::withMessages(['publish' => 'Set the exam start and end time before publishing.']);
        }        $questionCount = DB::table('tagore_olympiad_questions')->where('olympiad_id', $event->id)->count();
        $approvedCount = DB::table('tagore_olympiad_questions')->where('olympiad_id', $event->id)->whereNotNull('approved_at')->count();
        if ($questionCount < 1 || $approvedCount !== $questionCount) {
            throw ValidationException::withMessages(['publish' => 'Add at least one question and approve every question before publishing.']);
        }
        $questionMarks = (float) DB::table('tagore_olympiad_questions')->where('olympiad_id', $event->id)->whereNotNull('approved_at')->sum('marks');
        if (abs($questionMarks - (float) $event->max_marks) > 0.01) {
            throw ValidationException::withMessages(['publish' => 'The sum of approved question marks must equal the event maximum marks.']);
        }        DB::table('tagore_olympiads')->where('id', $event->id)->update(['status' => 'published', 'updated_at' => now()]);
        return redirect()->route('tagore.olympiad.index')->with('status', 'Olympiad published. Student registration/exam delivery remains a separate acceptance gate.');
    }
}