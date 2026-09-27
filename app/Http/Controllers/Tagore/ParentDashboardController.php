<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ParentDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->user()->id;
        abort_unless($this->roles($userId)->contains('PARENT'), 403);

        $children = DB::table('tagore_parent_students as ps')
            ->join('users as u', 'u.id', '=', 'ps.student_id')
            ->leftJoin('tagore_institutions as i', 'i.school_id', '=', 'u.school_id')
            ->where('ps.parent_user_id', $userId)
            ->where('ps.status', 'active')
            ->orderBy('u.name')
            ->get(['ps.student_id', 'ps.relationship', 'u.name', 'u.school_id', 'i.id as institution_id', 'i.display_name as institution']);

        $studentIds = $children->pluck('student_id');
        $feeSummary = DB::table('tagore_fee_obligations')
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, COALESCE(SUM(net_amount),0) payable, COALESCE(SUM(paid_amount),0) paid, COALESCE(SUM(outstanding_amount),0) outstanding')
            ->groupBy('student_id')->get()->keyBy('student_id');
        $attendanceSummary = DB::table('attendances')
            ->whereIn('user_id', $studentIds)
            ->selectRaw('user_id, COUNT(*) total, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) present')
            ->groupBy('user_id')->get()->keyBy('user_id');
        $results = DB::table('tagore_results')
            ->whereIn('student_id', $studentIds)->where('status', 'published')
            ->orderByDesc('id')->get(['student_id', 'exam_name', 'subject', 'marks', 'max_marks', 'grade']);

        $latestResults = $results->groupBy('student_id')->map(function ($rows) {
            $exam = $rows->first()->exam_name;
            $examRows = $rows->where('exam_name', $exam);
            $max = (float) $examRows->sum('max_marks');
            $marks = (float) $examRows->sum('marks');
            return (object) ['exam_name' => $exam, 'percentage' => $max > 0 ? round(($marks / $max) * 100, 1) : null, 'subjects' => $examRows->count()];
        });

        $cards = $children->map(function ($child) use ($feeSummary, $attendanceSummary, $latestResults) {
            $fees = $feeSummary->get($child->student_id);
            $attendance = $attendanceSummary->get($child->student_id);
            return (object) [
                'student_id' => $child->student_id,
                'name' => $child->name,
                'institution' => $child->institution,
                'relationship' => $child->relationship,
                'payable' => (float) ($fees->payable ?? 0),
                'paid' => (float) ($fees->paid ?? 0),
                'outstanding' => (float) ($fees->outstanding ?? 0),
                'attendance' => $attendance && (int) $attendance->total > 0 ? round(((int) $attendance->present / (int) $attendance->total) * 100, 1) : null,
                'present' => (int) ($attendance->present ?? 0),
                'attendance_total' => (int) ($attendance->total ?? 0),
                'latest_result' => $latestResults->get($child->student_id),
            ];
        });

        return view('tagore.parent-dashboard', compact('cards'));
    }

    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')->where('ur.user_id', $userId)->where('ur.status', 'active')->pluck('r.code')->unique()->values();
    }
}
