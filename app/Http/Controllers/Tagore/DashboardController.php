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
        $analyticsDays = (int) $request->input('period', 7);
        if (!in_array($analyticsDays, [7, 30, 90], true)) {
            $analyticsDays = 7;
        }
        $analyticsStart = now()->subDays($analyticsDays - 1)->startOfDay();
        $roles = $this->roles($userId);
        $isParent = $roles->contains('PARENT');
        $children = $this->childrenForUser($userId, $isParent);
        $institutions = DB::table('tagore_institutions as i')->join('schools as s', 's.id', '=', 'i.school_id')->where('i.status', 'active');
        if ($isParent) {
            $institutions->whereIn('i.id', function ($query) use ($userId) {
                $query->from('users as u')->join('tagore_parent_students as ps', 'ps.student_id', '=', 'u.id')->join('tagore_institutions as ci', 'ci.school_id', '=', 'u.school_id')->where('ps.parent_user_id', $userId)->where('ps.status', 'active')->select('ci.id');
            });
        } elseif (!$roles->contains('OWNER')) {
            $institutions->whereIn('i.id', DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')->whereNotNull('institution_id')->pluck('institution_id'));
        }
        $institutions = $institutions->orderBy('i.display_name')->get(['i.id', 'i.code', 'i.display_name', 's.name as school_name']);
        $institutionIds = $institutions->pluck('id');
        $studentIds = $children->pluck('student_id');
        $stats = [
            'institutions' => $institutionIds->count(),
            'children' => $children->count(),
            'pending_fees' => $isParent ? DB::table('tagore_fee_obligations')->whereIn('student_id', $studentIds)->whereIn('status', ['pending', 'partial', 'overdue'])->count() : DB::table('tagore_fee_obligations')->whereIn('institution_id', $institutionIds)->whereIn('status', ['pending', 'partial', 'overdue'])->count(),
            'open_feedback' => $isParent ? DB::table('tagore_feedback')->where('submitted_by', $userId)->whereIn('status', ['open', 'in_review'])->count() : DB::table('tagore_feedback')->whereIn('institution_id', $institutionIds)->whereIn('status', ['open', 'in_review'])->count(),
        ];
        $managerCommand = [
            'is_manager' => false,
            'action_items' => collect(),
            'departments' => collect(),
            'completion_rate' => 0,
            'active_staff' => 0,
            'employee_performance' => collect(),
            'workload_trend' => collect(),
            'manager_followups' => collect(),
            'workload_summary' => ['staff_with_active_work' => 0, 'average_active' => 0, 'max_active' => 0],
            'followups' => collect(),
        ];
        if (!$isParent && $roles->intersect(['OWNER','PRINCIPAL','COORDINATOR'])->isNotEmpty() && !empty($institutionIds)) {
            $managerCommand['is_manager'] = true;
            $taskBase = DB::table('tagore_tasks')->whereIn('institution_id', $institutionIds)->whereNotIn('status', ['completed','cancelled']);
            $stats['open_tasks'] = (clone $taskBase)->count();
            $stats['overdue_tasks'] = (clone $taskBase)->whereNotNull('due_at')->where('due_at','<',now())->count();

            $totalTasks = DB::table('tagore_tasks')->whereIn('institution_id', $institutionIds)->count();
            $completedTasks = DB::table('tagore_tasks')->whereIn('institution_id', $institutionIds)->where('status', 'completed')->count();
            $periodCreated = DB::table('tagore_tasks')->whereIn('institution_id', $institutionIds)->where('created_at', '>=', $analyticsStart)->count();
            $periodCompleted = DB::table('tagore_tasks')->whereIn('institution_id', $institutionIds)->where('status', 'completed')->whereNotNull('completed_at')->where('completed_at', '>=', $analyticsStart)->count();
            $managerCommand['analytics'] = [
                'days' => $analyticsDays,
                'start' => $analyticsStart,
                'created' => $periodCreated,
                'completed' => $periodCompleted,
                'net' => $periodCreated - $periodCompleted,
                'completion_rate' => $periodCreated > 0 ? round(($periodCompleted / $periodCreated) * 100, 1) : 0,
            ];

            $managerCommand['completion_rate'] = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

            $managerCommand['employee_performance'] = DB::table('users as u')
                ->joinSub(
                    DB::table('tagore_user_roles')
                        ->select('user_id')
                        ->whereIn('institution_id', $institutionIds)
                        ->where('status', 'active')
                        ->groupBy('user_id'),
                    'visible_staff',
                    'visible_staff.user_id',
                    '=',
                    'u.id'
                )
                ->leftJoin('tagore_tasks as t', function ($join) use ($institutionIds) {
                    $join->on('t.assigned_to', '=', 'u.id')->whereIn('t.institution_id', $institutionIds);
                })
                ->whereNotIn('u.usergroup_id', [6, 7])
                ->whereNull('u.deleted_at')
                ->select('u.id', 'u.name')
                ->selectRaw("SUM(CASE WHEN t.created_at >= ? THEN 1 ELSE 0 END) as assigned", [$analyticsStart])
                ->selectRaw("SUM(CASE WHEN t.status = 'completed' AND t.completed_at IS NOT NULL AND t.completed_at >= ? THEN 1 ELSE 0 END) as completed", [$analyticsStart])
                ->selectRaw("SUM(CASE WHEN t.status NOT IN ('completed','cancelled') AND t.due_at IS NOT NULL AND t.due_at < ? THEN 1 ELSE 0 END) as overdue", [now()])
                ->selectRaw("SUM(CASE WHEN t.status = 'blocked' THEN 1 ELSE 0 END) as blocked")
                ->selectRaw("SUM(CASE WHEN t.status IN ('open','in_progress','blocked') THEN 1 ELSE 0 END) as active")
                ->groupBy('u.id', 'u.name')
                ->orderByDesc('active')
                ->orderBy('u.name')
                ->limit(50)->get()
                ->map(function ($employee) {
                    $employee->completion_rate = (int) $employee->assigned > 0
                        ? round(((int) $employee->completed / (int) $employee->assigned) * 100, 1)
                        : 0;
                    return $employee;
                });

            $trendStart = $analyticsStart;
            $trendEnd = now()->endOfDay();
            $createdTrend = DB::table('tagore_tasks')
                ->whereIn('institution_id', $institutionIds)
                ->whereBetween('created_at', [$trendStart, $trendEnd])
                ->selectRaw('DATE(created_at) as day')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('day')
                ->pluck('total', 'day');
            $completedTrend = DB::table('tagore_tasks')
                ->whereIn('institution_id', $institutionIds)
                ->whereBetween('completed_at', [$trendStart, $trendEnd])
                ->selectRaw('DATE(completed_at) as day')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('day')
                ->pluck('total', 'day');
            $managerCommand['workload_trend'] = collect(range(0, $analyticsDays - 1))->map(function ($offset) use ($trendStart, $createdTrend, $completedTrend) {
                $day = $trendStart->copy()->addDays($offset)->format('Y-m-d');
                return (object) [
                    'day' => $day,
                    'created' => (int) ($createdTrend[$day] ?? 0),
                    'completed' => (int) ($completedTrend[$day] ?? 0),
                ];
            });
            $managerCommand['manager_followups'] = $managerCommand['employee_performance']
                ->filter(fn ($employee) => (int) $employee->blocked > 0 || (int) $employee->overdue > 0 || (int) $employee->active >= 8)
                ->map(function ($employee) {
                    $signals = [];
                    if ((int) $employee->blocked > 0) $signals[] = 'Blocked work';
                    if ((int) $employee->overdue > 0) $signals[] = 'Overdue work';
                    if ((int) $employee->active >= 8) $signals[] = '8+ active tasks';
                    $employee->follow_up = implode(' • ', $signals);
                    return $employee;
                })->values();

            $activeWorkloads = $managerCommand['employee_performance']->pluck('active')->map(fn ($value) => (int) $value);
            $managerCommand['workload_summary'] = [
                'staff_with_active_work' => $activeWorkloads->filter(fn ($value) => $value > 0)->count(),
                'average_active' => $activeWorkloads->isNotEmpty() ? round($activeWorkloads->avg(), 1) : 0,
                'max_active' => $activeWorkloads->max() ?? 0,
            ];

            $managerCommand['followups'] = DB::table('tagore_employee_reviews as r')
                ->join('users as e', 'e.id', '=', 'r.employee_id')
                ->join('users as m', 'm.id', '=', 'r.manager_id')
                ->leftJoin('tagore_tasks as t', 't.id', '=', 'r.task_id')
                ->whereIn('r.institution_id', $institutionIds)
                ->where('r.action_required', true)
                ->whereNull('r.completed_at')
                ->orderByRaw("CASE WHEN r.follow_up_at IS NULL THEN 2 WHEN r.follow_up_at < ? THEN 0 ELSE 1 END", [now()])
                ->orderBy('r.follow_up_at')
                ->limit(30)
                ->get(['r.id','r.employee_id','r.outcome','r.notes','r.follow_up_at','r.created_at','e.name as employee_name','m.name as manager_name','t.title as task_title'])
                ->map(function ($followup) {
                    $followup->state = $followup->follow_up_at && $followup->follow_up_at < now() ? 'overdue' : ($followup->follow_up_at ? 'due' : 'unscheduled');
                    return $followup;
                });

            $managerCommand['active_staff'] = DB::table('tagore_user_roles as ur')
                ->join('users as u', 'u.id', '=', 'ur.user_id')
                ->whereIn('ur.institution_id', $institutionIds)
                ->where('ur.status', 'active')
                ->whereNotIn('u.usergroup_id', [6, 7])
                ->whereNull('u.deleted_at')
                ->distinct('u.id')->count('u.id');

            $managerCommand['departments'] = DB::table('tagore_departments as d')
                ->leftJoin('tagore_tasks as t', function ($join) {
                    $join->on('t.department_id', '=', 'd.id')->whereNotIn('t.status', ['completed', 'cancelled']);
                })
                ->whereIn('d.institution_id', $institutionIds)
                ->where('d.status', 'active')
                ->groupBy('d.id', 'd.name')
                ->select('d.id', 'd.name')
                ->selectRaw('COUNT(t.id) as active')
                ->selectRaw("SUM(CASE WHEN t.due_at IS NOT NULL AND t.due_at < ? THEN 1 ELSE 0 END) as overdue", [now()])
                ->selectRaw("SUM(CASE WHEN t.status = 'blocked' THEN 1 ELSE 0 END) as blocked")
                ->orderByDesc('active')
                ->limit(12)->get();

            $managerCommand['action_items'] = DB::table('tagore_tasks as t')
                ->leftJoin('users as u', 'u.id', '=', 't.assigned_to')
                ->leftJoin('tagore_departments as d', 'd.id', '=', 't.department_id')
                ->whereIn('t.institution_id', $institutionIds)
                ->whereNotIn('t.status', ['completed', 'cancelled'])
                ->where(function ($q) {
                    $q->where('t.status', 'blocked')
                      ->orWhere(function ($q) {
                          $q->whereNotNull('t.due_at')->where('t.due_at', '<', now());
                      })
                      ->orWhere(function ($q) {
                          $q->whereNotNull('t.due_at')->whereBetween('t.due_at', [now(), now()->addDay()]);
                      })
                      ->orWhere('t.assigned_to', null);
                })
                ->select('t.id', 't.title', 't.status', 't.priority', 't.progress', 't.due_at',
                    'u.name as assignee', 'd.name as department')
                ->orderByRaw("case when t.status='blocked' then 0 when t.due_at < ? then 1 when t.assigned_to is null then 2 else 3 end", [now()])
                ->orderBy('t.due_at')
                ->limit(15)->get();
        } elseif (!$isParent && $roles->intersect(['TEACHER','ACCOUNTS'])->isNotEmpty() && !empty($institutionIds)) {
            $taskBase = DB::table('tagore_tasks')->whereIn('institution_id', $institutionIds)->whereNotIn('status', ['completed','cancelled'])
                ->where(fn($q)=>$q->where('assigned_to',$userId)->orWhere('created_by',$userId));
            $stats['open_tasks'] = (clone $taskBase)->count();
            $stats['overdue_tasks'] = (clone $taskBase)->whereNotNull('due_at')->where('due_at','<',now())->count();
        } else {
            $stats['open_tasks'] = 0;
            $stats['overdue_tasks'] = 0;
        }
        return view('tagore.dashboard', compact('roles', 'children', 'stats', 'institutions', 'managerCommand', 'analyticsDays'));
    }


    public function departmentProfile(Request $request, int $departmentId): View
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR'])->isNotEmpty(), 403);

        $analyticsDays = (int) $request->input('period', 7);
        if (!in_array($analyticsDays, [7, 30, 90], true)) $analyticsDays = 7;
        $analyticsStart = now()->subDays($analyticsDays - 1)->startOfDay();

        $institutionIds = DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')
            ->whereNotNull('institution_id')->pluck('institution_id');
        if ($roles->contains('OWNER')) {
            $institutionIds = DB::table('tagore_institutions')->where('status','active')->pluck('id');
        }

        $department = DB::table('tagore_departments as d')
            ->join('tagore_institutions as i', 'i.id', '=', 'd.institution_id')
            ->where('d.id', $departmentId)->where('d.status', 'active')->whereIn('d.institution_id', $institutionIds)
            ->first(['d.id','d.name','d.code','d.institution_id','i.display_name as institution']);
        abort_unless($department, 404);

        $taskQuery = DB::table('tagore_tasks')->where('department_id', $departmentId)->where('institution_id', $department->institution_id);
        $total = (clone $taskQuery)->count();
        $completed = (clone $taskQuery)->where('status', 'completed')->count();
        $stats = [
            'assigned'=>$total, 'active'=>(clone $taskQuery)->whereIn('status',['open','in_progress','blocked'])->count(),
            'completed'=>$completed, 'overdue'=>(clone $taskQuery)->whereNotIn('status',['completed','cancelled'])->whereNotNull('due_at')->where('due_at','<',now())->count(),
            'blocked'=>(clone $taskQuery)->where('status','blocked')->count(),
            'completion_rate'=>$total ? round(($completed/$total)*100,1) : 0,
        ];

        $periodStats = [
            'created'=>(clone $taskQuery)->where('created_at','>=',$analyticsStart)->count(),
            'completed'=>(clone $taskQuery)->where('completed_at','>=',$analyticsStart)->whereNotNull('completed_at')->count(),
        ];
        $periodStats['net']=$periodStats['created']-$periodStats['completed'];
        $periodStats['completion_rate']=$periodStats['created'] ? round(($periodStats['completed']/$periodStats['created'])*100,1) : 0;

        $employees = DB::table('tagore_user_departments as ud')
            ->join('users as u','u.id','=','ud.user_id')
            ->leftJoin('tagore_tasks as t',function($join) use($departmentId,$department){
                $join->on('t.assigned_to','=','u.id')->where('t.department_id','=',$departmentId)->where('t.institution_id','=',$department->institution_id);
            })
            ->where('ud.department_id',$departmentId)->where('ud.status','active')->whereNull('u.deleted_at')
            ->groupBy('u.id','u.name','ud.designation')->select('u.id','u.name','ud.designation')
            ->selectRaw('SUM(CASE WHEN t.created_at >= ? THEN 1 ELSE 0 END) as period_created',[$analyticsStart])
            ->selectRaw("SUM(CASE WHEN t.completed_at IS NOT NULL AND t.completed_at >= ? AND t.status='completed' THEN 1 ELSE 0 END) as period_completed",[$analyticsStart])
            ->selectRaw("SUM(CASE WHEN t.status IN ('open','in_progress','blocked') THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN t.status NOT IN ('completed','cancelled') AND t.due_at IS NOT NULL AND t.due_at < ? THEN 1 ELSE 0 END) as overdue",[now()])
            ->selectRaw("SUM(CASE WHEN t.status='blocked' THEN 1 ELSE 0 END) as blocked")
            ->orderByDesc('active')->orderBy('u.name')->get()->map(function($e){
                $e->period_completion_rate=(int)$e->period_created>0?round(((int)$e->period_completed/(int)$e->period_created)*100,1):0; return $e;
            });

        $employeeIds=$employees->pluck('id')->all() ?: [0];
        $followups=DB::table('tagore_employee_reviews as r')->join('users as e','e.id','=','r.employee_id')->leftJoin('tagore_tasks as t','t.id','=','r.task_id')
            ->where('r.institution_id',$department->institution_id)->whereNull('r.completed_at')->where('r.action_required',true)
            ->whereIn('r.employee_id',$employeeIds)->orderBy('r.follow_up_at')->limit(30)
            ->get(['r.id','r.outcome','r.notes','r.follow_up_at','e.name as employee_name','t.title as task_title']);

        $trendStart=$analyticsStart;
        $createdTrend=(clone $taskQuery)->whereBetween('created_at',[$trendStart,now()->endOfDay()])->selectRaw('DATE(created_at) day')->selectRaw('COUNT(*) total')->groupBy('day')->pluck('total','day');
        $completedTrend=(clone $taskQuery)->whereBetween('completed_at',[$trendStart,now()->endOfDay()])->selectRaw('DATE(completed_at) day')->selectRaw('COUNT(*) total')->groupBy('day')->pluck('total','day');
        $trend=collect(range(0,$analyticsDays-1))->map(function($offset) use($trendStart,$createdTrend,$completedTrend){
            $day=$trendStart->copy()->addDays($offset)->format('Y-m-d');
            return (object)['day'=>$day,'created'=>(int)($createdTrend[$day]??0),'completed'=>(int)($completedTrend[$day]??0)];
        });

        return view('tagore.departments.profile',compact('department','stats','periodStats','employees','followups','trend','analyticsDays'));
    }

    public function child(Request $request, int $studentId): View
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        $isParent = $roles->contains('PARENT');
        $isOwner = $roles->contains('OWNER');
        $isStaff = $roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER', 'ACCOUNTS'])->isNotEmpty();
        if ($isParent && !DB::table('tagore_parent_students')->where('parent_user_id', $userId)->where('student_id', $studentId)->where('status', 'active')->exists()) abort(403);
        if (!$isParent && !$isStaff && $studentId !== $userId) abort(403);
        $student = DB::table('users as u')->leftJoin('tagore_institutions as i', 'i.school_id', '=', 'u.school_id')->where('u.id', $studentId)->first(['u.id', 'u.name', 'u.school_id', 'i.id as institution_id', 'i.display_name as institution']);
        abort_unless($student, 404);
        if ($isStaff && !$isOwner && !DB::table('tagore_user_roles')->where('user_id', $userId)->where('institution_id', $student->institution_id)->where('status', 'active')->exists()) abort(403);
        $fees = DB::table('tagore_fee_obligations')->where('student_id', $studentId)->orderByDesc('due_date')->get();
        $attendance = DB::table('attendances')->where('user_id', $studentId)->selectRaw("count(*) as total, sum(case when status = 1 then 1 else 0 end) as present")->first();
        $attendancePercent = ($attendance && (int) $attendance->total > 0) ? round(((int) $attendance->present / (int) $attendance->total) * 100, 1) : null;
        $results = DB::table('tagore_results')->where('student_id', $studentId)->where('status', 'published')->orderBy('exam_name')->orderBy('subject')->get();
        $feedback = DB::table('tagore_feedback as f')->leftJoin('tagore_feedback_categories as c', 'c.id', '=', 'f.category_id')->where('f.student_id', $studentId)->orderByDesc('f.created_at')->get(['f.*', 'c.name as category_name']);
        return view('tagore.child', compact('student', 'fees', 'attendance', 'attendancePercent', 'results', 'feedback', 'roles'));
    }

    public function submitFeedback(Request $request, int $studentId)
    {
        $userId = (int) $request->user()->id;
        abort_unless(DB::table('tagore_parent_students')->where('parent_user_id', $userId)->where('student_id', $studentId)->where('status', 'active')->exists(), 403);
        $data = $request->validate(['subject' => ['required', 'string', 'max:150'], 'message' => ['required', 'string', 'max:5000'], 'category_id' => ['nullable', 'integer', 'exists:tagore_feedback_categories,id']]);
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
