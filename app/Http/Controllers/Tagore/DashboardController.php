<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        $isParent = $roles->contains('PARENT');
        $children = $this->childrenForUser($userId, $isParent);
        $institutionIds = DB::table('tagore_institutions')->where('status', 'active')->pluck('id');
        $studentIds = $children->pluck('student_id');

        $stats = [
            'institutions' => $institutionIds->count(),
            'children' => $children->count(),
            'pending_fees' => $isParent
                ? DB::table('tagore_fee_obligations')->whereIn('student_id', $studentIds)->whereIn('status', ['pending', 'partial', 'overdue'])->count()
                : DB::table('tagore_fee_obligations')->whereIn('institution_id', $institutionIds)->whereIn('status', ['pending', 'partial', 'overdue'])->count(),
            'open_feedback' => $isParent
                ? DB::table('tagore_feedback')->where('submitted_by', $userId)->whereIn('status', ['open', 'in_review'])->count()
                : DB::table('tagore_feedback')->whereIn('institution_id', $institutionIds)->whereIn('status', ['open', 'in_review'])->count(),
        ];

        return view('tagore.dashboard', compact('roles', 'children', 'stats'));
    }

    public function child(Request $request, int $studentId): View
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        $isParent = $roles->contains('PARENT');
        $isStaff = $roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER', 'ACCOUNTS'])->isNotEmpty();

        if ($isParent && !DB::table('tagore_parent_students')->where('parent_user_id', $userId)->where('student_id', $studentId)->where('status', 'active')->exists()) abort(403);
        if (!$isParent && !$isStaff && $studentId !== $userId) abort(403);

        $student = DB::table('users as u')->leftJoin('tagore_institutions as i', 'i.school_id', '=', 'u.school_id')
            ->where('u.id', $studentId)->first(['u.id', 'u.name', 'u.school_id', 'i.id as institution_id', 'i.display_name as institution']);
        abort_unless($student, 404);

        $fees = DB::table('tagore_fee_obligations')->where('student_id', $studentId)->orderByDesc('due_date')->get();
        $attendance = DB::table('attendances')->where('user_id', $studentId)->selectRaw("count(*) as total, sum(case when status = 1 then 1 else 0 end) as present")->first();
        $attendancePercent = ($attendance && (int) $attendance->total > 0) ? round(((int) $attendance->present / (int) $attendance->total) * 100, 1) : null;
        $results = DB::table('tagore_results')->where('student_id', $studentId)->where('status', 'published')->orderBy('exam_name')->orderBy('subject')->get();
        $feedback = DB::table('tagore_feedback as f')->leftJoin('tagore_feedback_categories as c', 'c.id', '=', 'f.category_id')
            ->where(function ($q) use ($userId, $studentId, $isStaff) {
                $q->where('f.student_id', $studentId);
                if (!$isStaff) $q->where('f.submitted_by', $userId);
            })->orderByDesc('f.created_at')->get(['f.*', 'c.name as category_name']);

        return view('tagore.child', compact('student', 'fees', 'attendancePercent', 'results', 'feedback', 'roles'));
    }

    public function submitFeedback(Request $request, int $studentId)
    {
        $userId = (int) $request->user()->id;
        abort_unless(DB::table('tagore_parent_students')->where('parent_user_id', $userId)->where('student_id', $studentId)->where('status', 'active')->exists(), 403);
        $data = $request->validate(['subject' => ['required', 'string', 'max:150'], 'message' => ['required', 'string', 'max:5000'], 'category_id' => ['nullable', 'integer']]);
        $institutionId = DB::table('tagore_institutions as i')->join('users as u', 'u.school_id', '=', 'i.school_id')->where('u.id', $studentId)->value('i.id');
        abort_unless($institutionId, 422);
        DB::table('tagore_feedback')->insert(['institution_id' => $institutionId, 'submitted_by' => $userId, 'student_id' => $studentId, 'category_id' => $data['category_id'] ?? null, 'subject' => $data['subject'], 'message' => $data['message'], 'priority' => 'normal', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Feedback submitted successfully.');
    }

    public function api(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        return response()->json(['success' => true, 'data' => ['user_id' => $userId, 'roles' => $this->roles($userId)->values(), 'children' => $this->childrenForUser($userId, true)]]);
    }

    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')->where('ur.user_id', $userId)->where('ur.status', 'active')->pluck('r.code')->unique()->values();
    }

    private function childrenForUser(int $userId, bool $parentOnly = true)
    {
        $query = DB::table('tagore_parent_students as ps')->join('users as u', 'u.id', '=', 'ps.student_id')->where('ps.status', 'active')->orderBy('u.name');
        if ($parentOnly) $query->where('ps.parent_user_id', $userId);
        return $query->get(['ps.student_id', 'ps.relationship', 'u.name', 'u.school_id']);
    }
}
