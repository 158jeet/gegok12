<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ParentOlympiadController extends Controller
{
    public function index(Request $request, int $studentId): View
    {
        $child = $this->assertParentChild($request, $studentId);
        $institution = $this->institutionForChild($child);
        $grade = $institution ? $this->studentGradeForInstitution($studentId, (int) $institution->id) : null;
        $events = collect();

        if ($institution && $grade !== null) {
            $events = DB::table('tagore_olympiads')->where('institution_id', $institution->id)
                ->where('status', 'published')->orderBy('exam_starts_at')->get()
                ->filter(function ($event) use ($grade) {
                    return in_array($grade, array_map('strval', json_decode($event->target_classes, true) ?: []), true);
                })->values();
        }

        $registrations = $events->isEmpty() ? collect() : DB::table('tagore_olympiad_registrations')
            ->where('student_user_id', $studentId)->whereIn('olympiad_id', $events->pluck('id'))->get()->keyBy('olympiad_id');
        $now = now();
        $events = $events->map(function ($event) use ($registrations, $now) {
            $registration = $registrations->get($event->id);
            $event->registration = $registration;
            $event->is_submitted = $registration && in_array($registration->status, ['submitted', 'submitted_late'], true);
            $event->can_register = !$registration
                && (!$event->registration_opens_at || $now->gte(Carbon::parse($event->registration_opens_at)))
                && (!$event->registration_closes_at || $now->lte(Carbon::parse($event->registration_closes_at)))
                && (!$event->exam_starts_at || $now->lt(Carbon::parse($event->exam_starts_at)));
            $event->can_launch = $registration && in_array($registration->status, ['registered', 'in_progress'], true)
                && $event->exam_starts_at && $event->exam_ends_at
                && $now->gte(Carbon::parse($event->exam_starts_at)) && $now->lte(Carbon::parse($event->exam_ends_at));
            return $event;
        });

        return view('tagore.olympiad.parent-index', compact('child', 'studentId', 'events', 'grade'));
    }

    public function register(Request $request, int $studentId, int $olympiadId)
    {
        $child = $this->assertParentChild($request, $studentId);
        $event = $this->eventForChild($child, $studentId, $olympiadId);
        $now = now();
        if ($event->registration_opens_at && $now->lt(Carbon::parse($event->registration_opens_at))) {
            throw ValidationException::withMessages(['registration' => 'Registration has not opened yet.']);
        }
        if ($event->registration_closes_at && $now->gt(Carbon::parse($event->registration_closes_at))) {
            throw ValidationException::withMessages(['registration' => 'The registration deadline has passed.']);
        }
        if ($event->exam_starts_at && $now->gte(Carbon::parse($event->exam_starts_at))) {
            throw ValidationException::withMessages(['registration' => 'Registration is closed because the exam has started.']);
        }

        $existing = DB::table('tagore_olympiad_registrations')->where('olympiad_id', $event->id)
            ->where('student_user_id', $studentId)->first();
        if (!$existing) {
            DB::table('tagore_olympiad_registrations')->insert([
                'olympiad_id' => $event->id, 'institution_id' => $event->institution_id,
                'student_user_id' => $studentId, 'registration_no' => strtoupper($event->code . '-' . Str::random(8)),
                'status' => 'registered', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return redirect()->route('tagore.parent.olympiad.index', ['studentId' => $studentId])
            ->with('status', 'Registration confirmed for ' . $event->title . '.');
    }

    public function launch(Request $request, int $studentId, int $olympiadId)
    {
        $child = $this->assertParentChild($request, $studentId);
        $event = $this->eventForChild($child, $studentId, $olympiadId);
        $registration = DB::table('tagore_olympiad_registrations')->where('olympiad_id', $event->id)
            ->where('student_user_id', $studentId)->first();
        abort_unless($registration, 403);
        if (in_array($registration->status, ['submitted', 'submitted_late'], true)) {
            return redirect()->route('tagore.parent.olympiad.result', ['studentId' => $studentId, 'olympiadId' => $olympiadId]);
        }
        if (!$event->exam_starts_at || !$event->exam_ends_at) {
            throw ValidationException::withMessages(['exam' => 'The exam schedule has not been configured.']);
        }
        $now = now();
        if ($now->lt(Carbon::parse($event->exam_starts_at))) {
            return redirect()->route('tagore.parent.olympiad.index', ['studentId' => $studentId])->with('status', 'The exam has not started yet.');
        }
        if ($now->gt(Carbon::parse($event->exam_ends_at))) {
            return redirect()->route('tagore.parent.olympiad.index', ['studentId' => $studentId])->with('status', 'The exam window has closed. Contact the school if assistance is needed.');
        }
        abort_unless(in_array($registration->status, ['registered', 'in_progress'], true), 409);

        $token = Str::random(40);
        $launches = (array) $request->session()->get('tagore_parent_olympiad_launches', []);
        $launches[$token] = [
            'parent_user_id' => (int) $request->user()->id, 'student_id' => $studentId,
            'olympiad_id' => $olympiadId, 'registration_id' => (int) $registration->id,
            'expires_at' => now()->addHours(4)->timestamp,
        ];
        $request->session()->put('tagore_parent_olympiad_launches', $launches);

        return redirect()->route('tagore.parent.olympiad.exam', [
            'studentId' => $studentId, 'olympiadId' => $olympiadId, 'token' => $token,
        ]);
    }

    public function exam(Request $request, int $studentId, int $olympiadId, string $token)
    {
        [$child, $event, $registration] = $this->resolveLaunch($request, $studentId, $olympiadId, $token);
        if (in_array($registration->status, ['submitted', 'submitted_late'], true)) {
            return redirect()->route('tagore.parent.olympiad.result', ['studentId' => $studentId, 'olympiadId' => $olympiadId]);
        }
        if (!$event->exam_starts_at || !$event->exam_ends_at) {
            throw ValidationException::withMessages(['exam' => 'The exam schedule has not been configured.']);
        }
        $now = now();
        if ($now->lt(Carbon::parse($event->exam_starts_at))) {
            return redirect()->route('tagore.parent.olympiad.index', ['studentId' => $studentId])->with('status', 'The exam has not started yet.');
        }
        if ($now->gt(Carbon::parse($event->exam_ends_at))) {
            return redirect()->route('tagore.parent.olympiad.index', ['studentId' => $studentId])->with('status', 'The exam window has closed. Contact the school if assistance is needed.');
        }
        if ($registration->status === 'registered') {
            DB::table('tagore_olympiad_registrations')->where('id', $registration->id)->where('status', 'registered')
                ->update(['status' => 'in_progress', 'started_at' => now(), 'updated_at' => now()]);
            $registration = DB::table('tagore_olympiad_registrations')->where('id', $registration->id)->first();
        }
        abort_unless($registration->status === 'in_progress' && $registration->started_at, 409);
        $questions = DB::table('tagore_olympiad_questions')->where('olympiad_id', $event->id)->whereNotNull('approved_at')
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'question_text', 'options', 'marks']);
        abort_unless($questions->isNotEmpty(), 409);
        $questions = $questions->map(function ($question) {
            $question->options = json_decode($question->options, true) ?: [];
            return $question;
        });
        $deadline = min(strtotime($event->exam_ends_at), strtotime($registration->started_at) + ((int) $event->duration_minutes * 60));
        $remainingSeconds = max(0, $deadline - time());
        $parentExamToken = $token;
        return view('tagore.olympiad.student-exam', compact('event', 'questions', 'registration', 'remainingSeconds', 'studentId', 'parentExamToken', 'child'));
    }

    public function saveAnswers(Request $request, int $studentId, int $olympiadId, string $token)
    {
        [, $event, $registration] = $this->resolveLaunch($request, $studentId, $olympiadId, $token);
        abort_unless($registration->status === 'in_progress' && $registration->started_at, 409);
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

    public function submit(Request $request, int $studentId, int $olympiadId, string $token)
    {
        [, $event, $registration] = $this->resolveLaunch($request, $studentId, $olympiadId, $token);
        if (in_array($registration->status, ['submitted', 'submitted_late'], true)) {
            return redirect()->route('tagore.parent.olympiad.result', ['studentId' => $studentId, 'olympiadId' => $olympiadId]);
        }
        abort_unless($registration->status === 'in_progress' && $registration->started_at, 409);
        $submittedAnswers = (array) $request->input('answers', []);
        $questions = DB::table('tagore_olympiad_questions')->where('olympiad_id', $event->id)->whereNotNull('approved_at')
            ->orderBy('sort_order')->get(['id', 'options', 'correct_option', 'marks']);
        $score = 0.0;
        $totalMarks = 0.0;
        $cleanAnswers = [];
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
                'answers' => json_encode($cleanAnswers), 'score' => round($score, 2),
                'total_marks' => round($totalMarks, 2), 'status' => $status, 'submitted_at' => now(), 'updated_at' => now(),
            ]);
        });
        return redirect()->route('tagore.parent.olympiad.result', ['studentId' => $studentId, 'olympiadId' => $olympiadId]);
    }

    public function result(Request $request, int $studentId, int $olympiadId): View
    {
        $child = $this->assertParentChild($request, $studentId);
        $event = $this->eventForChild($child, $studentId, $olympiadId);
        $registration = DB::table('tagore_olympiad_registrations')->where('olympiad_id', $event->id)
            ->where('student_user_id', $studentId)->first();
        abort_unless($registration, 404);
        if (!in_array($registration->status, ['submitted', 'submitted_late'], true)) {
            return redirect()->route('tagore.parent.olympiad.index', ['studentId' => $studentId])->with('status', 'The result will appear after the exam is submitted.');
        }
        return view('tagore.olympiad.parent-result', compact('child', 'studentId', 'event', 'registration'));
    }

    private function resolveLaunch(Request $request, int $studentId, int $olympiadId, string $token): array
    {
        $child = $this->assertParentChild($request, $studentId);
        $launches = (array) $request->session()->get('tagore_parent_olympiad_launches', []);
        $launch = $launches[$token] ?? null;
        abort_unless(is_array($launch)
            && (int) ($launch['parent_user_id'] ?? 0) === (int) $request->user()->id
            && (int) ($launch['student_id'] ?? 0) === $studentId
            && (int) ($launch['olympiad_id'] ?? 0) === $olympiadId
            && (int) ($launch['expires_at'] ?? 0) >= now()->timestamp, 403);
        $event = $this->eventForChild($child, $studentId, $olympiadId);
        $registration = DB::table('tagore_olympiad_registrations')->where('id', (int) $launch['registration_id'])
            ->where('olympiad_id', $event->id)->where('student_user_id', $studentId)->first();
        abort_unless($registration, 404);
        return [$child, $event, $registration];
    }

    private function assertParentChild(Request $request, int $studentId): object
    {
        $parentId = (int) $request->user()->id;
        $isParent = DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $parentId)->where('ur.status', 'active')->where('r.code', 'PARENT')->exists();
        abort_unless($isParent, 403);
        $child = DB::table('tagore_parent_students as ps')->join('users as u', 'u.id', '=', 'ps.student_id')
            ->where('ps.parent_user_id', $parentId)->where('ps.student_id', $studentId)->where('ps.status', 'active')
            ->first(['u.id as student_id', 'u.school_id', 'u.name']);
        abort_unless($child, 404);
        return $child;
    }

    private function institutionForChild(object $child): ?object
    {
        return DB::table('tagore_institutions')->where('school_id', $child->school_id)
            ->where('status', 'active')->orderBy('id')->first();
    }

    private function eventForChild(object $child, int $studentId, int $olympiadId): object
    {
        $institution = $this->institutionForChild($child);
        abort_unless($institution, 404);
        $event = DB::table('tagore_olympiads')->where('id', $olympiadId)
            ->where('institution_id', $institution->id)->where('status', 'published')->first();
        abort_unless($event, 404);
        $grade = $this->studentGradeForInstitution($studentId, (int) $institution->id);
        $classes = array_map('strval', json_decode($event->target_classes, true) ?: []);
        abort_unless($grade !== null && in_array($grade, $classes, true), 404);
        return $event;
    }

    private function studentGradeForInstitution(int $userId, int $institutionId): ?string
    {
        $schoolId = DB::table('tagore_institutions')->where('id', $institutionId)->value('school_id');
        if (!$schoolId) return null;
        $record = DB::table('student_academics as sa')->join('standards_link as sl', 'sl.id', '=', 'sa.standardLink_id')
            ->join('standards as s', 's.id', '=', 'sl.standard_id')
            ->where('sa.user_id', $userId)->where('sa.school_id', $schoolId)->where('sl.school_id', $schoolId)
            ->whereNull('sa.deleted_at')->whereNull('sl.deleted_at')->whereNull('s.deleted_at')
            ->where('sl.status', 1)->orderByDesc('sa.academic_year_id')
            ->select('s.name as grade_name', 's.order as grade_order')->first();
        if (!$record) return null;
        $digits = preg_replace('/\D+/', '', (string) $record->grade_name);
        $grade = (int) $digits;
        if ($grade < 1 || $grade > 12) $grade = (int) $record->grade_order - 3;
        return $grade >= 1 && $grade <= 12 ? (string) $grade : null;
    }
}
