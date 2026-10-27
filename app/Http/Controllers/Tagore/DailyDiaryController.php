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

        $query = DB::table('tagore_parent_students as ps')
            ->join('users as student', 'student.id', '=', 'ps.student_id')
            ->join('tagore_institutions as i', 'i.school_id', '=', 'student.school_id')
            ->where('ps.status', 'active')
            ->whereNull('student.deleted_at');

        if ($isParent && !$isSchool) {
            $query->where('ps.parent_user_id', $userId);
        } else {
            $ids = $this->institutionIds($userId, $roles);
            $query->whereIn('i.id', $ids);
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

        $link = DB::table('tagore_parent_students as ps')
            ->join('users as student', 'student.id', '=', 'ps.student_id')
            ->join('tagore_institutions as i', 'i.school_id', '=', 'student.school_id')
            ->where('ps.student_id', $studentId)->where('ps.status', 'active')
            ->whereNull('student.deleted_at')
            ->select('ps.parent_user_id', 'student.id as student_id', 'i.id as institution_id')->first();
        abort_unless($link, 404);

        if ($isParent && !$isSchool) {
            abort_unless(DB::table('tagore_parent_students')->where('parent_user_id', $userId)->where('student_id', $studentId)->where('status', 'active')->exists(), 403);
        } else {
            abort_unless(in_array((int) $link->institution_id, $this->institutionIds($userId, $roles), true), 403);
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

        if ($isParent) {
            $parentFields = [
                'study_hours' => $data['study_hours'] ?? null,
                'parent_feedback' => $data['parent_feedback'] ?? null,
                'parent_updated_by' => $userId,
            ];
        } else {
            $parentFields = [];
        }
        if ($isSchool) {
            $schoolFields = [
                'school_feedback' => $data['school_feedback'] ?? null,
                'homework' => $data['homework'] ?? null,
                'school_updated_by' => $userId,
            ];
        } else {
            $schoolFields = [];
        }

        $values = $parentFields + $schoolFields + ['institution_id' => (int) $link->institution_id, 'updated_at' => now()];
        if ($entry) {
            DB::table('tagore_daily_diary')->where('id', $entry->id)->update($values);
        } else {
            DB::table('tagore_daily_diary')->insert($values + $key + ['created_at' => now()]);
        }

        return redirect()->route('tagore.diary.index', ['student_id' => $studentId, 'date' => $data['diary_date']])->with('success', 'Daily diary saved.');
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
