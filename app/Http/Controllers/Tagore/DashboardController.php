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
        $roles = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('ur.status', 'active')
            ->pluck('r.name')
            ->unique()
            ->values();

        $institutions = DB::table('tagore_institutions as i')
            ->join('schools as s', 's.id', '=', 'i.school_id')
            ->where('i.status', 'active')
            ->orderBy('i.display_name')
            ->get(['i.id', 'i.code', 'i.display_name', 's.name as school_name']);

        $children = DB::table('tagore_parent_students as ps')
            ->join('users as u', 'u.id', '=', 'ps.student_id')
            ->where('ps.parent_user_id', $userId)
            ->where('ps.status', 'active')
            ->orderBy('u.name')
            ->get(['ps.student_id', 'ps.relationship', 'u.name']);

        $institutionIds = $institutions->pluck('id');
        $stats = [
            'institutions' => $institutions->count(),
            'children' => $children->count(),
            'pending_fees' => $institutionIds->isEmpty() ? 0 : DB::table('tagore_fee_obligations')->whereIn('institution_id', $institutionIds)->whereIn('status', ['pending', 'partial', 'overdue'])->count(),
            'open_feedback' => $institutionIds->isEmpty() ? 0 : DB::table('tagore_feedback')->whereIn('institution_id', $institutionIds)->whereIn('status', ['open', 'in_review'])->count(),
        ];

        return view('tagore.dashboard', compact('roles', 'institutions', 'children', 'stats'));
    }

    public function api(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $children = DB::table('tagore_parent_students as ps')
            ->join('users as u', 'u.id', '=', 'ps.student_id')
            ->where('ps.parent_user_id', $userId)
            ->where('ps.status', 'active')
            ->get(['ps.student_id', 'ps.relationship', 'u.name']);

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'children' => $children,
                'institutions' => DB::table('tagore_institutions')->where('status', 'active')->orderBy('display_name')->get(['id', 'code', 'display_name']),
            ],
        ]);
    }
}
