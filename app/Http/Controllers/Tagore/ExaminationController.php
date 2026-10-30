<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExaminationController extends Controller
{
    private const MANAGERS = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'IT_ADMIN'];
    private const MARKS_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER', 'IT_ADMIN'];

    public function index(Request $request)
    {
        [$roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(array_merge(self::MANAGERS, self::MARKS_ROLES))->isNotEmpty(), 403);

        $exams = DB::table('tagore_exams as e')
            ->join('tagore_institutions as i', 'i.id', '=', 'e.institution_id')
            ->join('academic_years as ay', 'ay.id', '=', 'e.academic_year_id')
            ->join('standards as s', 's.id', '=', 'e.standard_id')->join('standards_link as sl', 'sl.id', '=', 'e.standard_link_id')
            ->whereIn('e.institution_id', $institutionIds)
            ->orderByDesc('e.id')
            ->limit(100)
            ->get(['e.*', 'i.school_id as school_id', 'i.display_name as institution_name', 'ay.name as academic_year_name', 's.name as standard_name', 'sl.section_id as section_id']);

        $institutions = DB::table('tagore_institutions')->whereIn('id', $institutionIds)->where('status', 'active')->orderBy('display_name')->get(['id', 'school_id', 'display_name']);
        $academicYears = DB::table('academic_years')->whereIn('school_id', $institutions->pluck('school_id'))->whereNull('deleted_at')->orderByDesc('start_date')->get(['id', 'school_id', 'name']);
        $standardLinks = DB::table('standards_link as sl')->join('standards as s', 's.id', '=', 'sl.standard_id')->join('sections as sec', 'sec.id', '=', 'sl.section_id')->join('tagore_institutions as ti', 'ti.school_id', '=', 'sl.school_id')->whereIn('sl.school_id', $institutions->pluck('school_id'))->whereNull('sl.deleted_at')->where('sl.status', 1)->orderBy('s.order')->orderBy('sec.name')->get(['sl.id', 'sl.school_id', 'sl.academic_year_id', 'sl.standard_id', 'sl.section_id', 's.name as standard_name', 'sec.name as section_name', 'ti.display_name as institution_name']);
        $subjectOptions = DB::table('subjects')->whereIn('school_id', $institutions->pluck('school_id'))->whereNull('deleted_at')->where('status', 1)->orderBy('name')->get(['id', 'school_id', 'academic_year_id', 'standard_id', 'section_id', 'name']);
        $examSubjects = DB::table('tagore_exam_subjects')->join('tagore_exams', 'tagore_exams.id', '=', 'tagore_exam_subjects.exam_id')->orderBy('tagore_exam_subjects.exam_date')->get(['tagore_exam_subjects.*', 'tagore_exams.id as parent_exam_id'])->groupBy('parent_exam_id');
        return view('tagore.examinations.index', compact('exams', 'institutions', 'academicYears', 'standardLinks', 'subjectOptions', 'examSubjects', 'roles'));
    }

    public function store(Request $request)
    {
        [$roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGERS)->isNotEmpty(), 403);
        $data = $request->validate([
            'institution_id' => 'required|integer',
            'academic_year_id' => 'required|integer',
            'standard_link_id' => 'required|integer',
            'name' => 'required|string|max:120',
            'exam_type' => 'required|in:unit_test,half_yearly,annual,pre_board,board,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        abort_unless(in_array((int) $data['institution_id'], $institutionIds, true), 403);
        $institution = DB::table('tagore_institutions')->where('id', $data['institution_id'])->first();
        abort_unless($institution, 404);
        abort_unless(DB::table('academic_years')->where('id', $data['academic_year_id'])->where('school_id', $institution->school_id)->exists(), 422, 'Academic year does not belong to this institution.');
        $standardLink = DB::table('standards_link')->where('id', $data['standard_link_id'])->where('school_id', $institution->school_id)->where('academic_year_id', $data['academic_year_id'])->where('status', 1)->whereNull('deleted_at')->first();
        abort_unless($standardLink, 422, 'Class and section do not belong to this institution and academic year.');
        $data['standard_id'] = $standardLink->standard_id;

        $id = DB::table('tagore_exams')->insertGetId($data + ['status' => 'draft', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->event($id, $request, 'created', null, 'draft');
        return back()->with('success', 'Examination created. Add its subject schedule next.');
    }

    public function addSubject(Request $request, int $examId)
    {
        [$roles] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGERS)->isNotEmpty(), 403);
        $exam = $this->examForUser($request, $examId);
        abort_unless(in_array($exam->status, ['draft', 'marks_entry'], true), 409, 'Only draft or marks-entry examinations can be changed.');
        $data = $request->validate([
            'subject_id' => 'required|integer',
            'max_marks' => 'required|numeric|min:1|max:10000',
            'pass_marks' => 'required|numeric|min:0|lte:max_marks',
            'exam_date' => 'nullable|date|after_or_equal:' . $exam->start_date . '|before_or_equal:' . $exam->end_date,
            'start_time' => 'nullable|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:1|max:600',
        ]);
        $subject = DB::table('subjects')->where('id', $data['subject_id'])->where('school_id', $exam->school_id)->where('academic_year_id', $exam->academic_year_id)->where('standard_id', $exam->standard_id)->where('section_id', DB::table('standards_link')->where('id', $exam->standard_link_id)->value('section_id'))->whereNull('deleted_at')->first();
        abort_unless($subject, 422, 'Select a subject assigned to this class and academic year.');
        DB::table('tagore_exam_subjects')->insert($data + ['exam_id' => $examId, 'subject_name' => $subject->name, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Subject added to the examination.');
    }

    public function marksEntry(Request $request, int $examSubjectId)
    {
        [$roles] = $this->context($request);
        abort_unless($roles->intersect(self::MARKS_ROLES)->isNotEmpty(), 403);
        $subject = DB::table('tagore_exam_subjects as es')
            ->join('tagore_exams as e', 'e.id', '=', 'es.exam_id')
            ->join('tagore_institutions as i', 'i.id', '=', 'e.institution_id')
            ->where('es.id', $examSubjectId)
            ->first(['es.*', 'e.id as exam_id', 'e.name as exam_name', 'e.institution_id', 'e.academic_year_id', 'e.standard_id', 'e.standard_link_id', 'e.status', 'i.school_id']);
        abort_unless($subject && $this->canAccessInstitution($request, (int) $subject->institution_id), 404);
        $this->assertSubjectPermission($request, $roles, $subject);
        abort_unless(in_array($subject->status, ['draft', 'marks_entry'], true), 409, 'Marks are locked after submission for moderation.');
        $students = DB::table('student_academic as sa')->join('users as u', 'u.id', '=', 'sa.user_id')
            ->where('u.school_id', $subject->school_id)->where('sa.academic_year_id', $subject->academic_year_id)
            ->where('sa.standardLink_id', $subject->standard_link_id)
            ->where('u.usergroup_id', 6)->whereNull('u.deleted_at')->whereNull('sa.deleted_at')
            ->orderBy('sa.roll_number')->orderBy('u.name')->get(['u.id', 'u.name', 'sa.roll_number']);
        $saved = DB::table('tagore_exam_marks')->where('exam_subject_id', $examSubjectId)->get()->keyBy('student_id');
        return view('tagore.examinations.marks', compact('subject', 'students', 'saved'));
    }

    public function marks(Request $request, int $examSubjectId)
    {
        [$roles] = $this->context($request);
        abort_unless($roles->intersect(self::MARKS_ROLES)->isNotEmpty(), 403);
        $subject = DB::table('tagore_exam_subjects as es')->join('tagore_exams as e', 'e.id', '=', 'es.exam_id')->join('tagore_institutions as i', 'i.id', '=', 'e.institution_id')->where('es.id', $examSubjectId)->first(['es.*', 'e.id as exam_id', 'e.institution_id', 'e.academic_year_id', 'e.standard_id', 'e.standard_link_id', 'e.status', 'i.school_id']);
        abort_unless($subject && $this->canAccessInstitution($request, (int) $subject->institution_id), 404);
        abort_unless(in_array($subject->status, ['draft', 'marks_entry'], true), 409, 'Marks are locked after submission for moderation.');
        $data = $request->validate(['marks' => 'required|array|min:1', 'marks.*.student_id' => 'required|integer', 'marks.*.marks_obtained' => 'nullable|numeric|min:0|max:' . $subject->max_marks, 'marks.*.is_absent' => 'nullable|boolean', 'marks.*.remarks' => 'nullable|string|max:500']);
        $validStudents = DB::table('student_academic as sa')->join('users as u', 'u.id', '=', 'sa.user_id')
            ->where('u.school_id', $this->schoolIdForInstitution((int) $subject->institution_id))
            ->where('sa.academic_year_id', $subject->academic_year_id)->where('sa.standardLink_id', $subject->standard_link_id)
            ->where('u.usergroup_id', 6)->whereNull('u.deleted_at')->whereNull('sa.deleted_at')->pluck('u.id')->map(fn ($id) => (int) $id)->all();
        DB::transaction(function () use ($data, $validStudents, $examSubjectId, $request) {
            foreach ($data['marks'] as $row) {
                $studentId = (int) $row['student_id'];
                abort_unless(in_array($studentId, $validStudents, true), 422, 'A student is outside this examination class or academic year.');
                $absent = (bool) ($row['is_absent'] ?? false);
                if (!$absent && (!array_key_exists('marks_obtained', $row) || $row['marks_obtained'] === null || $row['marks_obtained'] === '')) {
                    throw ValidationException::withMessages(['marks' => 'Enter marks or mark the student absent.']);
                }
                DB::table('tagore_exam_marks')->updateOrInsert(
                    ['exam_subject_id' => $examSubjectId, 'student_id' => $studentId],
                    ['marks_obtained' => $absent ? null : $row['marks_obtained'], 'is_absent' => $absent, 'remarks' => $row['remarks'] ?? null, 'entered_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });
        DB::table('tagore_exams')->where('id', $subject->exam_id)->where('status', 'draft')->update(['status' => 'marks_entry', 'updated_at' => now()]);
        return back()->with('success', 'Marks saved.');
    }

    public function submit(Request $request, int $examId)
    {
        [$roles] = $this->context($request);
        abort_unless($roles->intersect(self::MARKS_ROLES)->isNotEmpty(), 403);
        $exam = $this->examForUser($request, $examId);
        abort_unless(in_array($exam->status, ['draft', 'marks_entry'], true), 409);
        $subjects = DB::table('tagore_exam_subjects')->where('exam_id', $examId)->get();
        abort_if($subjects->isEmpty(), 422, 'Add at least one subject before submission.');
        foreach ($subjects as $subject) {
            $expected = $this->studentsForExam($exam)->count();
            $entered = DB::table('tagore_exam_marks')->where('exam_subject_id', $subject->id)->count();
            abort_if($expected === 0 || $entered !== $expected, 422, 'Complete marks entry for every enrolled student in every subject.');
        }
        DB::table('tagore_exams')->where('id', $examId)->update(['status' => 'submitted', 'submitted_by' => $request->user()->id, 'submitted_at' => now(), 'updated_at' => now()]);
        $this->event($examId, $request, 'submitted', $exam->status, 'submitted');
        return back()->with('success', 'Marks submitted for moderation. Editing is now locked.');
    }

    public function moderate(Request $request, int $examId)
    {
        [$roles] = $this->context($request);
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'IT_ADMIN'])->isNotEmpty(), 403);
        $exam = $this->examForUser($request, $examId);
        abort_unless($exam->status === 'submitted', 409, 'Only submitted results can be moderated.');
        DB::table('tagore_exams')->where('id', $examId)->update(['status' => 'moderated', 'moderated_by' => $request->user()->id, 'moderated_at' => now(), 'updated_at' => now()]);
        $this->event($examId, $request, 'moderated', 'submitted', 'moderated');
        return back()->with('success', 'Examination moderated. It is ready to publish.');
    }

    public function publish(Request $request, int $examId)
    {
        [$roles] = $this->context($request);
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'IT_ADMIN'])->isNotEmpty(), 403);
        $exam = $this->examForUser($request, $examId);
        abort_unless($exam->status === 'moderated', 409, 'Moderation is required before publishing.');
        DB::transaction(function () use ($exam, $examId, $request) {
            $subjects = DB::table('tagore_exam_subjects')->where('exam_id', $examId)->get();
            foreach ($subjects as $subject) {
                $marks = DB::table('tagore_exam_marks')->where('exam_subject_id', $subject->id)->get();
                foreach ($marks as $mark) {
                    $grade = $mark->is_absent ? 'AB' : ($mark->marks_obtained >= $subject->pass_marks ? 'PASS' : 'FAIL');
                    DB::table('tagore_results')->updateOrInsert(
                        ['exam_id' => $examId, 'student_id' => $mark->student_id, 'institution_id' => $exam->institution_id, 'academic_year_id' => $exam->academic_year_id, 'exam_name' => $exam->name, 'subject' => $subject->subject_name],
                        ['marks' => $mark->marks_obtained ?? 0, 'max_marks' => $subject->max_marks, 'grade' => $grade, 'status' => 'published', 'published_at' => now(), 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            }
            DB::table('tagore_exams')->where('id', $examId)->update(['status' => 'published', 'published_by' => $request->user()->id, 'published_at' => now(), 'updated_at' => now()]);
            $this->event($examId, $request, 'published', 'moderated', 'published');
        });
        return back()->with('success', 'Results published to the student and parent portals.');
    }

    public function withdraw(Request $request, int $examId)
    {
        [$roles] = $this->context($request);
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'IT_ADMIN'])->isNotEmpty(), 403);
        $exam = $this->examForUser($request, $examId);
        abort_unless($exam->status === 'published', 409, 'Only published results can be withdrawn.');
        DB::transaction(function () use ($exam, $examId, $request) {
            DB::table('tagore_results')->where('exam_id', $examId)->update(['status' => 'withdrawn', 'updated_at' => now()]);
            DB::table('tagore_exams')->where('id', $examId)->update(['status' => 'withdrawn', 'withdrawn_at' => now(), 'updated_at' => now()]);
            $this->event($examId, $request, 'withdrawn', 'published', 'withdrawn');
        });
        return back()->with('success', 'Results withdrawn from parent and student portals.');
    }

    private function context(Request $request): array
    {
        $roles = DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')->where('ur.user_id', $request->user()->id)->where('ur.status', 'active')->pluck('r.code')->unique()->values();
        $ids = $roles->contains('OWNER')
            ? DB::table('tagore_institutions')->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : DB::table('tagore_user_roles')->where('user_id', $request->user()->id)->where('status', 'active')->whereNotNull('institution_id')->pluck('institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        return [$roles, $ids];
    }

    private function assertSubjectPermission(Request $request, $roles, object $subject): void
    {
        if ($roles->contains('TEACHER') && $roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'IT_ADMIN'])->isEmpty()) {
            abort_unless(DB::table('class_teacher_links')
                ->where('teacher_id', $request->user()->id)
                ->where('subject_id', $subject->subject_id)
                ->where('standardLink_id', $subject->standard_link_id)
                ->where('academic_year_id', $subject->academic_year_id)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->exists(), 403, 'You are not assigned to this subject and class.');
        }
    }

    private function canAccessInstitution(Request $request, int $institutionId): bool
    {
        [, $ids] = $this->context($request);
        return in_array($institutionId, $ids, true);
    }

    private function examForUser(Request $request, int $examId): object
    {
        $exam = DB::table('tagore_exams as e')->join('tagore_institutions as i', 'i.id', '=', 'e.institution_id')->where('e.id', $examId)->whereNull('i.deleted_at')->first(['e.*', 'i.school_id']);
        abort_unless($exam && $this->canAccessInstitution($request, (int) $exam->institution_id), 404);
        return $exam;
    }

    private function schoolIdForInstitution(int $institutionId): int
    {
        return (int) DB::table('tagore_institutions')->where('id', $institutionId)->value('school_id');
    }

    private function studentsForExam(object $exam)
    {
        return DB::table('student_academic as sa')->join('users as u', 'u.id', '=', 'sa.user_id')
            ->where('u.school_id', $exam->school_id)->where('sa.academic_year_id', $exam->academic_year_id)
            ->where('sa.standardLink_id', $exam->standard_link_id)->where('u.usergroup_id', 6)->whereNull('u.deleted_at')->whereNull('sa.deleted_at')->distinct()->select('u.id');
    }

    private function event(int $examId, Request $request, string $event, ?string $from, ?string $to): void
    {
        DB::table('tagore_exam_events')->insert(['exam_id' => $examId, 'user_id' => $request->user()->id, 'event' => $event, 'from_status' => $from, 'to_status' => $to, 'created_at' => now(), 'updated_at' => now()]);
    }
}
