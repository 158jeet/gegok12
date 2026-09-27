<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffController extends Controller
{
    private const MANAGER_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR'];

    public function leave(Request $request): View
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGER_ROLES)->isNotEmpty(), 403);

        $schoolIds = DB::table('tagore_institutions')
            ->whereIn('id', $institutionIds)->where('status', 'active')->pluck('school_id')->all();

        $query = DB::table('teacher_leave_applications as l')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('users as a', 'a.id', '=', 'l.approved_by')
            ->leftJoin('leave_types as lt', 'lt.id', '=', 'l.leave_type_id')
            ->whereIn('l.school_id', $schoolIds)
            ->whereNull('l.deleted_at');

        if ($request->filled('status')) {
            $query->where('l.status', $request->string('status')->toString());
        } else {
            $query->whereIn('l.status', ['pending', 'approved']);
        }

        $applications = $query->orderByRaw("case when l.status='pending' then 0 else 1 end")
            ->orderBy('l.from_date')->limit(200)
            ->get([
                'l.id','l.user_id','l.from_date','l.to_date','l.session','l.remarks','l.comments',
                'l.status','l.approved_on','u.name as employee_name','u.email',
                'a.name as approver_name','lt.name as leave_type'
            ]);

        $pending = DB::table('teacher_leave_applications as l')
            ->whereIn('l.school_id', $schoolIds)->whereNull('l.deleted_at')->where('l.status', 'pending')->count();
        $approved = DB::table('teacher_leave_applications as l')
            ->whereIn('l.school_id', $schoolIds)->whereNull('l.deleted_at')->where('l.status', 'approved')->count();
        $today = DB::table('teacher_leave_applications as l')
            ->whereIn('l.school_id', $schoolIds)->whereNull('l.deleted_at')
            ->where('l.status', 'approved')->whereDate('l.from_date', '<=', today())->whereDate('l.to_date', '>=', today())->count();

        return view('tagore.staff.leave', compact('applications','pending','approved','today','roles'));
    }

    public function decideLeave(Request $request, int $leaveId)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGER_ROLES)->isNotEmpty(), 403);

        $schoolIds = DB::table('tagore_institutions')->whereIn('id', $institutionIds)->pluck('school_id')->all();
        $leave = DB::table('teacher_leave_applications')->where('id', $leaveId)->whereIn('school_id', $schoolIds)->whereNull('deleted_at')->first();
        abort_unless($leave, 404);
        abort_unless($leave->status === 'pending', 422, 'Only pending leave applications can be decided.');

        $data = $request->validate([
            'decision' => ['required','in:approved,cancelled'],
            'comments' => ['nullable','string','max:2000'],
        ]);

        DB::table('teacher_leave_applications')->where('id',$leaveId)->update([
            'status' => $data['decision'],
            'approved_by' => $userId,
            'approved_on' => now()->toDateString(),
            'comments' => $data['comments'] ?? null,
            'updated_at' => now(),
        ]);

        return back()->with('success', $data['decision'] === 'approved' ? 'Leave approved.' : 'Leave application cancelled.');
    }

    private function context(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $roles = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')
            ->pluck('r.code')->unique()->values();

        $institutionIds = $roles->contains('OWNER')
            ? DB::table('tagore_institutions')->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all()
            : DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')
                ->whereNotNull('institution_id')->pluck('institution_id')->map(fn($id)=>(int)$id)->unique()->values()->all();

        return [$userId,$roles,$institutionIds];
    }
}
