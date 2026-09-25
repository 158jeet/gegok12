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

        $assignees = DB::table('users as u')
            ->join('tagore_user_roles as ur', 'ur.user_id', '=', 'u.id')
            ->whereIn('ur.institution_id', $institutionIds)
            ->where('ur.status', 'active')
            ->whereIn('u.usergroup_id', [4, 5, 11])
            ->whereNull('u.deleted_at')
            ->select('u.id', 'u.name')
            ->distinct()
            ->orderBy('u.name')
            ->limit(250)
            ->get();

        return view('tagore.tasks.index', compact('tasks', 'stats', 'assignees', 'institutions', 'institutionIds', 'selectedInstitutionId', 'workload', 'unassignedWorkload'));
    }

    public function store(Request $request)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless(app(\App\Services\Tagore\ScopeService::class)->can($userId, 'task.manage'), 403);

        $data = $request->validate([
            'institution_id' => ['required', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'due_at' => ['nullable', 'date'],
        ]);

        $institutionId = (int) $data['institution_id'];
        abort_unless(in_array($institutionId, $institutionIds, true), 403);

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
        ]);

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

        $completedAt = $data['status'] === 'completed' ? now() : null;
        if ($data['status'] === 'completed') $data['progress'] = 100;

        $task->update($data + ['completed_at' => $completedAt]);

        return back()->with('success', 'Task updated.');
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
