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
            ->where('ps.parent_user_id', $userId)
            ->where('ps.status', 'active')
            ->where('u.status', 'active')
            ->whereNull('u.deleted_at')
            ->orderBy('u.name')
            ->get([
                'ps.student_id',
                'ps.relationship',
                'u.name',
                'u.school_id',
            ])
            ->map(function ($child) {
                $child->institution = DB::table('tagore_institutions')
                    ->where('school_id', $child->school_id)
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->value('display_name');

                return $child;
            });

        $studentIds = $children->pluck('student_id');

        $feeSummary = DB::table('tagore_fee_obligations as o')
            ->join('tagore_institutions as i', 'i.id', '=', 'o.institution_id')
            ->whereIn('o.student_id', $studentIds)
            ->whereIn('o.status', ['pending', 'partial', 'overdue', 'paid'])
            ->where('i.status', 'active')
            ->whereNull('i.deleted_at')
            ->selectRaw('o.student_id, COALESCE(SUM(o.net_amount),0) payable, COALESCE(SUM(o.paid_amount),0) paid, COALESCE(SUM(o.outstanding_amount),0) outstanding')
            ->groupBy('o.student_id')
            ->get()
            ->keyBy('student_id');

        $feeObligations = DB::table('tagore_fee_obligations as o')
            ->join('users as u', 'u.id', '=', 'o.student_id')
            ->join('tagore_institutions as i', 'i.id', '=', 'o.institution_id')
            ->whereIn('o.student_id', $studentIds)
            ->where('u.status', 'active')
            ->whereNull('u.deleted_at')
            ->where('i.status', 'active')
            ->whereNull('i.deleted_at')
            ->where('o.outstanding_amount', '>', 0)
            ->whereIn('o.status', ['pending', 'partial', 'overdue'])
            ->orderBy('o.due_date')
            ->orderBy('o.id')
            ->get([
                'o.id as fee_obligation_id',
                'o.student_id',
                'u.name as student_name',
                'o.institution_id',
                'i.display_name as institution',
                'o.due_date',
                'o.outstanding_amount',
                'o.status',
            ]);

        $reservedAmounts = collect();
        if ($feeObligations->isNotEmpty()) {
            $reservedAmounts = DB::table('tagore_payment_order_allocations as a')
                ->join('tagore_payment_orders as p', 'p.id', '=', 'a.payment_order_id')
                ->whereIn('p.status', ['created', 'payment_initiated', 'review_required'])
                ->where(function ($query) { $query->where('p.status', 'review_required')->orWhere('p.expires_at', '>', now()); })
                ->whereIn('a.fee_obligation_id', $feeObligations->pluck('fee_obligation_id'))
                ->groupBy('a.fee_obligation_id')
                ->select('a.fee_obligation_id', DB::raw('SUM(a.amount) as reserved_amount'))
                ->pluck('reserved_amount', 'a.fee_obligation_id');
        }

        $feeObligations->transform(function ($obligation) use ($reservedAmounts) {
            $obligation->reserved_amount = round((float) ($reservedAmounts[$obligation->fee_obligation_id] ?? 0), 2);
            $obligation->available_amount = max(0, round((float) $obligation->outstanding_amount - $obligation->reserved_amount, 2));

            return $obligation;
        });

        $attendanceSummary = DB::table('attendances')
            ->whereIn('user_id', $studentIds)
            ->selectRaw('user_id, COUNT(*) total, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) present')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $results = DB::table('tagore_results')
            ->whereIn('student_id', $studentIds)
            ->where('status', 'published')
            ->orderByDesc('id')
            ->get(['student_id', 'exam_name', 'subject', 'marks', 'max_marks', 'grade']);

        $latestResults = $results->groupBy('student_id')->map(function ($rows) {
            $exam = $rows->first()->exam_name;
            $examRows = $rows->where('exam_name', $exam);
            $max = (float) $examRows->sum('max_marks');
            $marks = (float) $examRows->sum('marks');

            return (object) [
                'exam_name' => $exam,
                'percentage' => $max > 0 ? round(($marks / $max) * 100, 1) : null,
                'subjects' => $examRows->count(),
            ];
        });

        $familySummary = (object) [
            'children_count' => $children->count(),
            'payable' => round((float) $feeSummary->sum('payable'), 2),
            'paid' => round((float) $feeSummary->sum('paid'), 2),
            'outstanding' => round((float) $feeSummary->sum('outstanding'), 2),
            'children_with_due' => $children->filter(function ($child) use ($feeSummary) {
                return (float) ($feeSummary->get($child->student_id)->outstanding ?? 0) > 0.009;
            })->count(),
        ];

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
                'attendance' => $attendance && (int) $attendance->total > 0
                    ? round(((int) $attendance->present / (int) $attendance->total) * 100, 1)
                    : null,
                'present' => (int) ($attendance->present ?? 0),
                'attendance_total' => (int) ($attendance->total ?? 0),
                'latest_result' => $latestResults->get($child->student_id),
            ];
        });

        return view('tagore.parent-dashboard', compact('cards', 'familySummary', 'feeObligations'));
    }

    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('ur.status', 'active')
            ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
            ->pluck('r.code')
            ->unique()
            ->values();
    }
}
