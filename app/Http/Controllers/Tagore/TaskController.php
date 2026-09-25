<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Models\TagoreTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskController extends Controller
{
    private const STAFF_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER', 'ACCOUNTS'];

    public function index(Request $request): View
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless(app(\App\Services\Tagore\ScopeService::class)->can($userId, 'task.view'), 403);

        $query = TagoreTask::query()
            ->whereIn('institution_id', $institutionIds)
            ->orderByRaw("case when status='open' then 0 when status='in_progress' then 1 when status='blocked' then 2 else 3 end")
            ->orderByRaw('case when due_at is null then 1 else 0 end')
            ->orderBy('due_at')
            ->orderByDesc('id');

        if (!$roles->contains('OWNER')) {
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

        $tasks = $query->paginate(30)->withQueryString();

        $base = TagoreTask::whereIn('institution_id', $institutionIds);
        if (!$roles->contains('OWNER')) {
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

        return view('tagore.tasks.index', compact('tasks', 'stats', 'assignees', 'institutionIds'));
    }

    public function store(Request $request)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::STAFF_ROLES)->isNotEmpty(), 403);

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
        abort_unless($roles->intersect(self::STAFF_ROLES)->isNotEmpty(), 403);

        $task = TagoreTask::findOrFail($taskId);
        abort_unless(in_array((int) $task->institution_id, $institutionIds, true), 403);
        if (!$roles->contains('OWNER')) {
            abort_unless((int) $task->assigned_to === $userId || (int) $task->created_by === $userId, 403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,blocked,completed,cancelled'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

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
