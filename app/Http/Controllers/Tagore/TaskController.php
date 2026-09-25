<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Models\TagoreTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskController extends Controller
{
    private const MANAGER_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR'];


    public function employeeProfile(Request $request, int $employeeId): View
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGER_ROLES)->isNotEmpty(), 403);

        $employee = DB::table('users as u')
            ->join('tagore_user_roles as ur', 'ur.user_id', '=', 'u.id')
            ->leftJoin('tagore_user_departments as ud', function ($join) {
                $join->on('ud.user_id', '=', 'u.id')
                    ->where('ud.status', 'active')
                    ->where('ud.is_primary', true);
            })
            ->leftJoin('tagore_departments as d', 'd.id', '=', 'ud.department_id')
            ->leftJoin('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('u.id', $employeeId)
            ->whereIn('ur.institution_id', $institutionIds)
            ->where('ur.status', 'active')
            ->whereNull('u.deleted_at')
            ->select('u.id','u.name','u.email','ud.designation','d.name as department','r.code as role')
            ->first();
        abort_unless($employee, 404);

        $tasks = TagoreTask::query()->whereIn('institution_id', $institutionIds)->where('assigned_to', $employeeId)->orderByRaw("case when status='open' then 0 when status='in_progress' then 1 when status='blocked' then 2 else 3 end")->orderByDesc('id')->limit(100)->get();
        $taskStats = [
            'assigned' => TagoreTask::whereIn('institution_id', $institutionIds)->where('assigned_to', $employeeId)->count(),
            'active' => TagoreTask::whereIn('institution_id', $institutionIds)->where('assigned_to', $employeeId)->whereIn('status',['open','in_progress','blocked'])->count(),
            'completed' => TagoreTask::whereIn('institution_id', $institutionIds)->where('assigned_to', $employeeId)->where('status','completed')->count(),
            'overdue' => TagoreTask::whereIn('institution_id', $institutionIds)->where('assigned_to', $employeeId)->whereNotIn('status',['completed','cancelled'])->whereNotNull('due_at')->where('due_at','<',now())->count(),
            'blocked' => TagoreTask::whereIn('institution_id', $institutionIds)->where('assigned_to', $employeeId)->where('status','blocked')->count(),
        ];
        $taskStats['completion_rate'] = $taskStats['assigned'] > 0 ? round(($taskStats['completed'] / $taskStats['assigned']) * 100, 1) : 0;

        $reviews = DB::table('tagore_employee_reviews as r')->join('users as m','m.id','=','r.manager_id')->leftJoin('tagore_tasks as t','t.id','=','r.task_id')->whereIn('r.institution_id',$institutionIds)->where('r.employee_id',$employeeId)->orderByDesc('r.created_at')->limit(50)->get(['r.id','r.review_type','r.outcome','r.notes','r.action_required','r.follow_up_at','r.completed_at','r.created_at','m.name as manager_name','t.title as task_title']);

        $activity = DB::table('tagore_task_events as e')->leftJoin('users as a','a.id','=','e.actor_id')->leftJoin('tagore_tasks as t','t.id','=','e.task_id')->whereIn('e.institution_id',$institutionIds)->whereIn('e.task_id', $tasks->pluck('id')->all() ?: [0])->orderByDesc('e.created_at')->limit(100)->get(['e.id','e.event_type','e.metadata','e.created_at','a.name as actor_name','t.title as task_title']);

        $trend = collect([7,30,90])->mapWithKeys(function ($days) use ($employeeId, $institutionIds) {
            $from = now()->subDays($days)->startOfDay();
            return [$days => [
                'created' => TagoreTask::whereIn('institution_id',$institutionIds)->where('assigned_to',$employeeId)->where('created_at','>=',$from)->count(),
                'completed' => TagoreTask::whereIn('institution_id',$institutionIds)->where('assigned_to',$employeeId)->where('status','completed')->whereNotNull('completed_at')->where('completed_at','>=',$from)->count(),
            ]];
        });

        $team = DB::table('tagore_tasks as t')->whereIn('t.institution_id',$institutionIds)->whereNotNull('t.assigned_to')->selectRaw('COUNT(*) as assigned')->selectRaw("SUM(CASE WHEN t.status IN ('open','in_progress','blocked') THEN 1 ELSE 0 END) as active')->selectRaw("SUM(CASE WHEN t.status='completed' THEN 1 ELSE 0 END) as completed")->first();
        $teamMembers = DB::table('tagore_user_roles as ur')->whereIn('ur.institution_id',$institutionIds)->where('ur.status','active')->distinct('ur.user_id')->count('ur.user_id');
        $teamAverageActive = $teamMembers > 0 ? round(((int) $team->active / $teamMembers), 1) : 0;

        return view('tagore.tasks.employee', compact('employee','taskStats','tasks','reviews','activity','trend','teamAverageActive'));
    }

    public function index(Request $request): View
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless(app(\App\Services\Tagore\ScopeService::class)->can($userId, 'task.view'), 403);

        $selectedInstitutionId = $request->filled('institution_id') ? (int) $request->integer('institution_id') : null;
        $visibleInstitutionIds = $selectedInstitutionId !== null
            ? array_values(array_intersect($institutionIds, [$selectedInstitutionId]))
            : $institutionIds;
        abort_unless($visibleInstitutionIds !== [], 403);

        $query = TagoreTask::query()
            ->whereIn('institution_id', $visibleInstitutionIds)
            ->orderByRaw("case when status='open' then 0 when status='in_progress' then 1 when status='blocked' then 2 else 3 end")
            ->orderByRaw('case when due_at is null then 1 else 0 end')
            ->orderBy('due_at')
            ->orderByDesc('id');

        if (!$roles->intersect(self::MANAGER_ROLES)->isNotEmpty()) {
            $query->where(function ($q) use ($userId) {
                $q->where('assigned_to', $userId)->orWhere('created_by', $userId);
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('q')) {
            $needle = trim($request->string('q')->toString());
            $query->where('title', 'like', "%{$needle}%");
        }
        if ($request->filled('assigned_to')) {
            $assignedTo = $request->string('assigned_to')->toString();
            $query->where('assigned_to', $assignedTo === 'unassigned' ? null : (int) $assignedTo);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', (int) $request->integer('department_id'));
        }

        $tasks = $query->paginate(30)->withQueryString();

        $base = TagoreTask::whereIn('institution_id', $visibleInstitutionIds);
        if (!$roles->intersect(self::MANAGER_ROLES)->isNotEmpty()) {
            $base->where(function ($q) use ($userId) {
                $q->where('assigned_to', $userId)->orWhere('created_by', $userId);
            });
        }

        $stats = [
            'open' => (clone $base)->whereIn('status', ['open', 'in_progress', 'blocked'])->count(),
            'due_today' => (clone $base)->whereNotIn('status', ['completed', 'cancelled'])
                ->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'overdue' => (clone $base)->whereNotIn('status', ['completed', 'cancelled'])
                ->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ];

        $institutions = DB::table('tagore_institutions')->whereIn('id', $institutionIds)->where('status', 'active')->orderBy('display_name')->get(['id', 'display_name']);
        $departments = DB::table('tagore_departments')->whereIn('institution_id', $visibleInstitutionIds)->where('status', 'active')->orderBy('name')->get(['id', 'institution_id', 'name']);

        $departmentWorkload = DB::table('tagore_departments as d')
            ->leftJoinSub(
                DB::table('tagore_tasks')
                    ->select('department_id')
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("SUM(CASE WHEN status IN ('open','in_progress','blocked') THEN 1 ELSE 0 END) as active")
                    ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
                    ->selectRaw("SUM(CASE WHEN status NOT IN ('completed','cancelled') AND due_at IS NOT NULL AND due_at < ? THEN 1 ELSE 0 END) as overdue", [now()])
                    ->selectRaw("SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked")
                    ->whereIn('institution_id', $visibleInstitutionIds)
                    ->whereNotNull('department_id')
                    ->groupBy('department_id'),
                'dw',
                'dw.department_id',
                '=',
                'd.id'
            )
            ->whereIn('d.institution_id', $visibleInstitutionIds)
            ->where('d.status', 'active')
            ->select('d.id', 'd.name', 'd.institution_id')
            ->selectRaw('COALESCE(dw.total, 0) as total')
            ->selectRaw('COALESCE(dw.active, 0) as active')
            ->selectRaw('COALESCE(dw.completed, 0) as completed')
            ->selectRaw('COALESCE(dw.overdue, 0) as overdue')
            ->selectRaw('COALESCE(dw.blocked, 0) as blocked')
            ->orderByDesc('active')
            ->orderBy('d.name')
            ->get();

        $workload = DB::table('users as u')
            ->leftJoinSub(
                DB::table('tagore_tasks')
                    ->select('institution_id', 'assigned_to')
                    ->selectRaw("COUNT(*) as total")
                    ->selectRaw("SUM(CASE WHEN status IN ('open','in_progress','blocked') THEN 1 ELSE 0 END) as active")
                    ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
                    ->selectRaw("SUM(CASE WHEN status NOT IN ('completed','cancelled') AND due_at IS NOT NULL AND due_at < ? THEN 1 ELSE 0 END) as overdue", [now()])
                    ->selectRaw("SUM(CASE WHEN status NOT IN ('completed','cancelled') AND due_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_today", [now()->startOfDay(), now()->endOfDay()])
                    ->whereIn('institution_id', $visibleInstitutionIds)
                    ->groupBy('institution_id', 'assigned_to'),
                'tw',
                function ($join) {
                    $join->on('tw.assigned_to', '=', 'u.id');
                }
            )
            ->joinSub(
                DB::table('tagore_user_roles')
                    ->select('user_id')
                    ->whereIn('institution_id', $visibleInstitutionIds)
                    ->where('status', 'active')
                    ->groupBy('user_id'),
                'visible_staff',
                'visible_staff.user_id',
                '=',
                'u.id'
            )
            ->whereNotIn('u.usergroup_id', [6, 7])
            ->whereNull('u.deleted_at')
            ->select('u.id', 'u.name')
            ->selectRaw('COALESCE(SUM(tw.total), 0) as total')
            ->selectRaw('COALESCE(SUM(tw.active), 0) as active')
            ->selectRaw('COALESCE(SUM(tw.completed), 0) as completed')
            ->selectRaw('COALESCE(SUM(tw.overdue), 0) as overdue')
            ->selectRaw('COALESCE(SUM(tw.due_today), 0) as due_today')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('active')
            ->orderBy('u.name')
            ->limit(500)
            ->get();

        $unassignedWorkload = TagoreTask::whereIn('institution_id', $visibleInstitutionIds)
            ->whereNull('assigned_to')
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status IN ('open','in_progress','blocked') THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('completed','cancelled') AND due_at IS NOT NULL AND due_at < ? THEN 1 ELSE 0 END) as overdue", [now()])
            ->first();

        $managerReviews = $roles->intersect(self::MANAGER_ROLES)->isNotEmpty()
            ? $this->managerReviews($visibleInstitutionIds)
            : collect();

        $recentActivity = DB::table('tagore_task_events as e')
            ->join('tagore_tasks as t', 't.id', '=', 'e.task_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'e.actor_id')
            ->whereIn('e.institution_id', $visibleInstitutionIds)
            ->orderByDesc('e.created_at')->orderByDesc('e.id')->limit(20)
            ->get(['e.id','e.task_id','e.event_type','e.metadata','e.created_at','t.title as task_title','actor.name as actor_name']);

        $assignees = DB::table('users as u')
            ->join('tagore_user_roles as ur', 'ur.user_id', '=', 'u.id')
            ->whereIn('ur.institution_id', $institutionIds)
            ->where('ur.status', 'active')
            ->whereNotIn('u.usergroup_id', [6, 7])
            ->whereNull('u.deleted_at')
            ->select('u.id', 'u.name')
            ->distinct()
            ->orderBy('u.name')
            ->limit(250)
            ->get();

        return view('tagore.tasks.index', compact('tasks', 'stats', 'assignees', 'institutions', 'institutionIds', 'selectedInstitutionId', 'workload', 'unassignedWorkload', 'departments', 'departmentWorkload', 'recentActivity', 'managerReviews', 'roles'));
    }

    public function store(Request $request)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless(app(\App\Services\Tagore\ScopeService::class)->can($userId, 'task.manage'), 403);

        $data = $request->validate([
            'institution_id' => ['required', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'due_at' => ['nullable', 'date'],
        ]);

        $institutionId = (int) $data['institution_id'];
        abort_unless(in_array($institutionId, $institutionIds, true), 403);

        if (!empty($data['department_id'])) {
            abort_unless(
                DB::table('tagore_departments')->where('id', (int) $data['department_id'])
                    ->where('institution_id', $institutionId)->where('status', 'active')->exists(),
                422,
                'The department must belong to the selected institution.'
            );
        } elseif (!empty($data['assigned_to'])) {
            $data['department_id'] = DB::table('tagore_user_departments as ud')
                ->join('tagore_departments as d', 'd.id', '=', 'ud.department_id')
                ->where('ud.user_id', (int) $data['assigned_to'])
                ->where('ud.status', 'active')->where('ud.is_primary', true)
                ->where('d.institution_id', $institutionId)->where('d.status', 'active')
                ->value('d.id');
        }

        if (!empty($data['assigned_to'])) {
            abort_unless(
                DB::table('tagore_user_roles')->where('user_id', (int) $data['assigned_to'])
                    ->where('institution_id', $institutionId)->where('status', 'active')->exists(),
                422,
                'The assignee must belong to the selected institution.'
            );
        }

        $task = TagoreTask::create($data + [
            'created_by' => $userId,
            'status' => 'open',
            'progress' => 0,
        ]);

        $this->recordEvent($task, $userId, 'created', [
            'title' => $task->title,
            'assigned_to' => $task->assigned_to,
            'department_id' => $task->department_id,
            'priority' => $task->priority,
            'due_at' => $task->due_at?->toISOString(),
        ]);

        return back()->with('success', "Task #{$task->id} created.");
    }

    public function update(Request $request, int $taskId)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless(app(\App\Services\Tagore\ScopeService::class)->can($userId, 'task.manage'), 403);

        $task = TagoreTask::findOrFail($taskId);
        abort_unless(in_array((int) $task->institution_id, $institutionIds, true), 403);
        if (!$roles->intersect(self::MANAGER_ROLES)->isNotEmpty()) {
            abort_unless((int) $task->assigned_to === $userId || (int) $task->created_by === $userId, 403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,blocked,completed,cancelled'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'assigned_to' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ]);

        if (!empty($data['department_id'])) {
            abort_unless(DB::table('tagore_departments')->where('id', (int) $data['department_id'])
                ->where('institution_id', (int) $task->institution_id)->where('status', 'active')->exists(), 422);
        }

        if (!empty($data['assigned_to'])) {
            abort_unless(
                DB::table('tagore_user_roles')
                    ->where('user_id', (int) $data['assigned_to'])
                    ->where('institution_id', (int) $task->institution_id)
                    ->where('status', 'active')
                    ->exists(),
                422,
                'The assignee must belong to the task institution.'
            );
        }

        $before = [
            'status' => $task->status,
            'progress' => (int) $task->progress,
            'assigned_to' => $task->assigned_to,
            'department_id' => $task->department_id,
        ];

        $completedAt = $data['status'] === 'completed' ? ($task->completed_at ?: now()) : null;
        if ($data['status'] === 'completed') $data['progress'] = 100;

        $task->update($data + ['completed_at' => $completedAt]);

        $changes = [];
        foreach (['status', 'progress', 'assigned_to', 'department_id'] as $field) {
            $old = $before[$field];
            $new = $task->{$field};
            if ((string) $old !== (string) $new) {
                $changes[$field] = ['from' => $old, 'to' => $new];
            }
        }
        if ($changes) {
            $this->recordEvent($task, $userId, 'updated', ['changes' => $changes]);
        }
        if (($before['assigned_to'] ?? null) != $task->assigned_to) {
            $this->recordEvent($task, $userId, 'reassigned', [
                'from' => $before['assigned_to'],
                'to' => $task->assigned_to,
            ]);
        }
        if ($before['status'] !== $task->status) {
            $this->recordEvent($task, $userId, 'status_changed', [
                'from' => $before['status'],
                'to' => $task->status,
            ]);
        }
        if ($before['progress'] !== (int) $task->progress) {
            $this->recordEvent($task, $userId, 'progress_changed', [
                'from' => $before['progress'],
                'to' => (int) $task->progress,
            ]);
        }

        return back()->with('success', 'Task updated.');
    }



    public function completeReview(Request $request, int $reviewId)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGER_ROLES)->isNotEmpty(), 403);

        $review = DB::table('tagore_employee_reviews')->where('id', $reviewId)->first();
        abort_unless($review && in_array((int) $review->institution_id, $institutionIds, true), 404);

        if ($review->completed_at !== null) {
            return back()->with('success', 'Follow-up was already closed.');
        }

        DB::table('tagore_employee_reviews')->where('id', $reviewId)->update([
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        if ($review->task_id) {
            $task = TagoreTask::find($review->task_id);
            if ($task) {
                $this->recordEvent($task, $userId, 'follow_up_completed', ['review_id' => $reviewId]);
            }
        }

        return back()->with('success', 'Manager follow-up closed.');
    }


    public function review(Request $request, int $taskId)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGER_ROLES)->isNotEmpty(), 403);

        $task = TagoreTask::findOrFail($taskId);
        abort_unless(in_array((int) $task->institution_id, $institutionIds, true), 403);
        abort_unless($task->assigned_to, 422, 'A review requires an assigned employee.');

        $data = $request->validate([
            'review_type' => ['required', 'in:follow_up,performance,recognition,corrective'],
            'outcome' => ['required', 'in:note,positive,needs_attention,action_required'],
            'notes' => ['required', 'string', 'max:5000'],
            'action_required' => ['nullable', 'boolean'],
            'follow_up_at' => ['nullable', 'date'],
        ]);

        $reviewId = DB::table('tagore_employee_reviews')->insertGetId([
            'institution_id' => $task->institution_id,
            'employee_id' => $task->assigned_to,
            'manager_id' => $userId,
            'task_id' => $task->id,
            'review_type' => $data['review_type'],
            'outcome' => $data['outcome'],
            'notes' => $data['notes'],
            'action_required' => !empty($data['action_required']),
            'follow_up_at' => $data['follow_up_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recordEvent($task, $userId, 'manager_reviewed', ['review_id' => $reviewId, 'outcome' => $data['outcome']]);

        return back()->with('success', 'Manager review recorded.');
    }

    private function managerReviews(array $institutionIds)
    {
        return DB::table('tagore_employee_reviews as r')
            ->join('users as e', 'e.id', '=', 'r.employee_id')
            ->join('users as m', 'm.id', '=', 'r.manager_id')
            ->leftJoin('tagore_tasks as t', 't.id', '=', 'r.task_id')
            ->whereIn('r.institution_id', $institutionIds)
            ->orderByDesc('r.created_at')->orderByDesc('r.id')->limit(20)
            ->get(['r.id','r.review_type','r.outcome','r.notes','r.action_required','r.follow_up_at','r.created_at',
                'e.name as employee_name','m.name as manager_name','t.title as task_title']);
    }


    private function recordEvent(TagoreTask $task, int $actorId, string $eventType, array $metadata = []): void
    {
        DB::table('tagore_task_events')->insert([
            'task_id' => $task->id,
            'institution_id' => $task->institution_id,
            'actor_id' => $actorId,
            'event_type' => $eventType,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function context(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $roles = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')
            ->pluck('r.code')->unique()->values();

        $institutionIds = $roles->contains('OWNER')
            ? DB::table('tagore_institutions')->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')
                ->whereNotNull('institution_id')->pluck('institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        return [$userId, $roles, $institutionIds];
    }
}
