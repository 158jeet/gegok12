<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DailyDiaryController extends Controller
{
    private const SCHOOL_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER'];

    public function index(Request $request): View
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        $isParent = $roles->contains('PARENT');
        $isSchool = $roles->intersect(self::SCHOOL_ROLES)->isNotEmpty();
        abort_unless($isParent || $isSchool, 403);

        $query = DB::table('users as student')
            ->join('tagore_institutions as i', 'i.school_id', '=', 'student.school_id')
            ->where('student.usergroup_id', 6)
            ->whereNull('student.deleted_at');

        if ($isParent && !$isSchool) {
            $query->join('tagore_parent_students as ps', function ($join) use ($userId) {
                $join->on('ps.student_id', '=', 'student.id')
                    ->where('ps.parent_user_id', '=', $userId)
                    ->where('ps.status', '=', 'active');
            });
        } else {
            $ids = $this->institutionIds($userId, $roles);
            $query->whereIn('i.id', $ids);
            $this->applySchoolStudentScope($query, $userId, $roles);
            if ($request->filled('student_id')) {
                $query->where('student.id', (int) $request->input('student_id'));
            }
        }

        $children = $query->select('student.id as student_id', 'student.name as student_name', 'i.id as institution_id', 'i.display_name as institution')
            ->distinct()->orderBy('student.name')->get();

        $childIds = $children->pluck('student_id')->map(fn ($id) => (int) $id)->all();
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $entries = DB::table('tagore_daily_diary')->whereIn('student_id', $childIds)
            ->whereBetween('diary_date', [now()->parse($date)->copy()->subDays(30)->toDateString(), $date])
            ->orderByDesc('diary_date')->get()->groupBy('student_id');

        return view('tagore.diary.index', compact('children', 'entries', 'date', 'isParent', 'isSchool'));
    }

    public function save(Request $request, int $studentId)
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        $isParent = $roles->contains('PARENT');
        $isSchool = $roles->intersect(self::SCHOOL_ROLES)->isNotEmpty();
        abort_unless($isParent || $isSchool, 403);

        $student = DB::table('users as student')
            ->join('tagore_institutions as i', 'i.school_id', '=', 'student.school_id')
            ->where('student.id', $studentId)
            ->where('student.usergroup_id', 6)
            ->whereNull('student.deleted_at')
            ->select('student.id', 'i.id as institution_id')
            ->first();
        abort_unless($student, 404);

        if ($isParent && !$isSchool) {
            abort_unless(DB::table('tagore_parent_students')
                ->where('parent_user_id', $userId)->where('student_id', $studentId)
                ->where('status', 'active')->exists(), 403);
        } else {
            abort_unless(in_array((int) $student->institution_id, $this->institutionIds($userId, $roles), true), 403);
            abort_unless($this->schoolUserCanAccessStudent($userId, $studentId, $roles, (int) $student->institution_id), 403);
        }

        // Accounts holding both Parent and school roles operate in the school context;
        // school submissions cannot overwrite the parent's independently owned fields.
        if (!$isParent || $isSchool) {
            $request->request->remove('study_hours');
            $request->request->remove('parent_feedback');
        }
        if (!$isSchool) {
            $request->request->remove('school_feedback');
            $request->request->remove('homework');
        }

        $data = $request->validate([
            'diary_date' => ['required', 'date', 'before_or_equal:today'],
            'study_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'parent_feedback' => ['nullable', 'string', 'max:3000'],
            'school_feedback' => ['nullable', 'string', 'max:3000'],
            'homework' => ['nullable', 'string', 'max:3000'],
        ]);

        $key = ['student_id' => $studentId, 'diary_date' => $data['diary_date']];
        $entry = DB::table('tagore_daily_diary')->where($key)->first();

        $values = [];
        if ($isParent && !$isSchool) {
            $values += [
                'study_hours' => $data['study_hours'] ?? null,
                'parent_feedback' => $data['parent_feedback'] ?? null,
                'parent_updated_by' => $userId,
            ];
        }
        if ($isSchool) {
            $values += [
                'school_feedback' => $data['school_feedback'] ?? null,
                'homework' => $data['homework'] ?? null,
                'school_updated_by' => $userId,
            ];
        }
        $values += ['institution_id' => (int) $student->institution_id, 'updated_at' => now()];

        if ($entry) {
            DB::table('tagore_daily_diary')->where('id', $entry->id)->update($values);
        } else {
            DB::table('tagore_daily_diary')->insert($values + $key + ['created_at' => now()]);
        }

        return redirect()->route('tagore.diary.index', ['student_id' => $studentId, 'date' => $data['diary_date']])->with('success', 'Daily diary saved.');
    }

    private function applySchoolStudentScope($query, int $userId, $roles): void
    {
        if ($roles->intersect(['OWNER', 'PRINCIPAL'])->isNotEmpty()) {
            return;
        }

        $query->whereExists(function ($academic) use ($userId, $roles) {
            $academic->selectRaw('1')->from('student_academics as sa')
                ->whereColumn('sa.user_id', 'student.id')
                ->whereNull('sa.deleted_at')
                ->whereRaw('sa.id = (select max(sa_latest.id) from student_academics as sa_latest where sa_latest.user_id = student.id and sa_latest.deleted_at is null)')
                ->where(function ($scope) use ($userId, $roles) {
                    if ($roles->contains('TEACHER')) {
                        $scope->whereExists(function ($assignment) use ($userId) {
                            $assignment->selectRaw('1')->from('class_teacher_links as ctl')
                                ->whereColumn('ctl.standardLink_id', 'sa.standardLink_id')
                                ->whereColumn('ctl.academic_year_id', 'sa.academic_year_id')
                                ->whereColumn('ctl.school_id', 'student.school_id')
                                ->where('ctl.teacher_id', $userId)->where('ctl.status', 1)
                                ->whereNull('ctl.deleted_at');
                        });
                    }
                    if ($roles->contains('COORDINATOR')) {
                        $feeViewId = DB::table('tagore_permissions')->where('code', 'fee.view')->value('id');
                        if ($feeViewId) {
                            $coordinatorScope = fn ($sub) => $sub->selectRaw('1')->from('tagore_user_scopes as us')
                                ->whereColumn('us.scope_id', 'sa.standardLink_id')
                                ->where('us.user_id', $userId)->where('us.scope_type', 'standard_link')
                                ->where('us.permission_id', $feeViewId);
                            if ($roles->contains('TEACHER')) {
                                $scope->orWhereExists($coordinatorScope);
                            } else {
                                $scope->whereExists($coordinatorScope);
                            }
                        } elseif (!$roles->contains('TEACHER')) {
                            $scope->whereRaw('1 = 0');
                        }
                    }
                });
        });
    }

    private function schoolUserCanAccessStudent(int $userId, int $studentId, $roles, int $institutionId): bool
    {
        if ($roles->intersect(['OWNER', 'PRINCIPAL'])->isNotEmpty()) {
            return true;
        }

        $student = DB::table('users')->where('id', $studentId)->first(['school_id']);
        if (!$student) return false;

        $academic = DB::table('student_academics as sa')
            ->where('sa.user_id', $studentId)->where('sa.school_id', $student->school_id)
            ->whereNull('sa.deleted_at')->orderByDesc('sa.id')->first(['sa.standardLink_id', 'sa.academic_year_id']);
        if (!$academic) return false;

        if ($roles->contains('TEACHER') && DB::table('class_teacher_links')
            ->where('teacher_id', $userId)->where('school_id', $student->school_id)
            ->where('standardLink_id', $academic->standardLink_id)
            ->where('academic_year_id', $academic->academic_year_id)
            ->where('status', 1)->whereNull('deleted_at')->exists()) {
            return true;
        }

        if ($roles->contains('COORDINATOR')) {
            $feeViewId = DB::table('tagore_permissions')->where('code', 'fee.view')->value('id');
            return $feeViewId && DB::table('tagore_user_scopes')
                ->where('user_id', $userId)->where('scope_type', 'standard_link')
                ->where('scope_id', $academic->standardLink_id)->where('permission_id', $feeViewId)->exists();
        }

        return false;
    }

    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')->pluck('r.code')->unique()->values();
    }

    private function institutionIds(int $userId, $roles): array
    {
        if ($roles->contains('OWNER')) {
            return DB::table('tagore_institutions')->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        return DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')
            ->whereNotNull('institution_id')->pluck('institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}
