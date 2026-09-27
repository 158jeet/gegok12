<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffController extends Controller
{
    private const MANAGER_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR'];

    public function self(Request $request): View
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->isNotEmpty(), 403);

        $user = DB::table('users')->where('id', $userId)->whereNull('deleted_at')->first(['id','name','email','school_id']);
        abort_unless($user, 404);

        $institution = DB::table('tagore_institutions')->whereIn('id', $institutionIds)->where('school_id', $user->school_id)->where('status','active')->first(['id','display_name','school_id']);
        abort_unless($institution, 403);

        $academicYearId = DB::table('academic_years')->where('school_id',$user->school_id)->where('status','active')->orderByDesc('id')->value('id');
        $leaveTypes = DB::table('leave_types')->where('school_id',$user->school_id)->where('status',1)->whereNull('deleted_at')->orderBy('name')->get(['id','name','max_no_of_days']);
        $leaves = DB::table('teacher_leave_applications as l')
            ->leftJoin('leave_types as lt','lt.id','=','l.leave_type_id')
            ->leftJoin('users as a','a.id','=','l.approved_by')
            ->where('l.user_id',$userId)->where('l.school_id',$user->school_id)->whereNull('l.deleted_at')
            ->orderByDesc('l.from_date')->limit(50)
            ->get(['l.id','l.from_date','l.to_date','l.session','l.remarks','l.comments','l.status','l.approved_on','lt.name as leave_type','a.name as approver_name']);

        $tasks = DB::table('tagore_tasks as t')
            ->leftJoin('tagore_departments as d','d.id','=','t.department_id')
            ->whereIn('t.institution_id',$institutionIds)->where('t.assigned_to',$userId)
            ->orderByRaw("case when t.status in ('open','in_progress','blocked') then 0 else 1 end")
            ->orderBy('t.due_at')->limit(50)
            ->get(['t.id','t.title','t.status','t.priority','t.progress','t.due_at','d.name as department']);

        $reviews = DB::table('tagore_employee_reviews as r')
            ->join('users as m','m.id','=','r.manager_id')
            ->leftJoin('tagore_tasks as t','t.id','=','r.task_id')
            ->whereIn('r.institution_id',$institutionIds)->where('r.employee_id',$userId)
            ->orderByDesc('r.created_at')->limit(30)
            ->get(['r.id','r.review_type','r.outcome','r.notes','r.action_required','r.follow_up_at','r.completed_at','r.created_at','m.name as manager_name','t.title as task_title']);

        $attendance = DB::table('attendances')->where('user_id',$userId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as present')
            ->first();
        $attendancePercent = ($attendance && (int)$attendance->total > 0) ? round(((int)$attendance->present / (int)$attendance->total) * 100, 1) : null;

        return view('tagore.staff.self', compact('user','roles','institution','academicYearId','leaveTypes','leaves','tasks','reviews','attendance','attendancePercent'));
    }

    public function applyLeave(Request $request)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        abort_unless($roles->isNotEmpty(), 403);

        $user = DB::table('users')->where('id',$userId)->whereNull('deleted_at')->first(['id','school_id']);
        abort_unless($user,404);
        $institutionId = (int) DB::table('tagore_institutions')->whereIn('id',$institutionIds)->where('school_id',$user->school_id)->where('status','active')->value('id');
        abort_unless($institutionId,403);

        $data = $request->validate([
            'leave_type_id' => ['required','integer'],
            'from_date' => ['required','date'],
            'to_date' => ['required','date','after_or_equal:from_date'],
            'session' => ['required','in:forenoon,afternoon,day'],
            'remarks' => ['nullable','string','max:2000'],
        ]);

        $leaveType = DB::table('leave_types')->where('id',$data['leave_type_id'])->where('school_id',$user->school_id)->where('status',1)->whereNull('deleted_at')->first();
        abort_unless($leaveType,422,'Invalid leave type for your institution.');
        $academicYearId = DB::table('academic_years')->where('school_id',$user->school_id)->where('status','active')->orderByDesc('id')->value('id');
        abort_unless($academicYearId,422,'No active academic year is configured.');

        $overlap = DB::table('teacher_leave_applications')->where('user_id',$userId)->where('school_id',$user->school_id)->whereNull('deleted_at')
            ->whereIn('status',['pending','approved'])
            ->whereDate('from_date','<=',$data['to_date'])->whereDate('to_date','>=',$data['from_date'])->exists();
        abort_if($overlap,422,'You already have a pending or approved leave overlapping these dates.');

        DB::table('teacher_leave_applications')->insert([
            'school_id'=>$user->school_id,'academic_year_id'=>$academicYearId,'user_id'=>$userId,
            'from_date'=>$data['from_date'],'to_date'=>$data['to_date'],'session'=>$data['session'],
            'remarks'=>$data['remarks'] ?? null,'leave_type_id'=>$data['leave_type_id'],'status'=>'pending',
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        return back()->with('success','Leave application submitted for approval.');
    }

    public function cancelOwnLeave(Request $request, int $leaveId)
    {
        [$userId] = $this->context($request);
        $leave = DB::table('teacher_leave_applications')->where('id',$leaveId)->where('user_id',$userId)->whereNull('deleted_at')->first();
        abort_unless($leave,404);
        abort_unless($leave->status === 'pending',422,'Only pending leave applications can be cancelled.');

        DB::table('teacher_leave_applications')->where('id',$leaveId)->update(['status'=>'cancelled','updated_at'=>now()]);
        return back()->with('success','Leave application cancelled.');
    }

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
