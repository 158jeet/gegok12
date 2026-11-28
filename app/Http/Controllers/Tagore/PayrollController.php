<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        [$roles,$institutionIds] = $this->scopedContext($request, ['PRINCIPAL','ACCOUNTS','HR']);

        $runs=DB::table('tagore_payroll_runs')->whereIn('institution_id',$institutionIds)->orderByDesc('month')->orderByDesc('id')->limit(100)->get();
        $institutions=DB::table('tagore_institutions')->whereIn('id',$institutionIds)->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        $employees=DB::table('users')->whereIn('school_id',DB::table('tagore_institutions')->whereIn('id',$institutionIds)->pluck('school_id'))
            ->whereIn('usergroup_id',[5,8,10,11,12,13])->where('status','active')->whereNull('deleted_at')->orderBy('name')->limit(500)->get(['id','name','email']);
        return view('tagore.payroll.index',compact('runs','institutions','employees','institutionIds'));
    }

    public function generate(Request $request, PayrollService $service)
    {
        [$roles,$institutionIds] = $this->scopedContext($request, ['PRINCIPAL','ACCOUNTS','HR']);
        $data=$request->validate([
            'institution_id'=>['required','integer'],
            'month'=>['required','date_format:Y-m'],
            'employees_json'=>['required','json'],
        ]);
        abort_unless(in_array((int)$data['institution_id'],$institutionIds,true),403);
        $employees=json_decode($data['employees_json'],true);
        abort_unless(is_array($employees),422);
        $runId=$service->generate((int)$data['institution_id'],$data['month'],$employees,(int)$request->user()->id);
        return back()->with('success','Payroll generated successfully (#'.$runId.').');
    }

    public function approve(Request $request,int $runId,PayrollService $service)
    {
        [$roles,$institutionIds]=$this->scopedContext($request, ['PRINCIPAL','ACCOUNTS']);
        $run=DB::table('tagore_payroll_runs')->where('id',$runId)->first();
        abort_unless($run && in_array((int)$run->institution_id,$institutionIds,true),404);
        $service->approve($runId,(int)$request->user()->id);
        return back()->with('success','Payroll approved.');
    }

    public function markPaid(Request $request,int $runId,PayrollService $service)
    {
        [$roles,$institutionIds]=$this->scopedContext($request, ['ACCOUNTS']);
        $run=DB::table('tagore_payroll_runs')->where('id',$runId)->first();
        abort_unless($run && in_array((int)$run->institution_id,$institutionIds,true),404);
        $service->markPaid($runId);
        return back()->with('success','Payroll marked paid.');
    }

    public function payslip(Request $request,int $itemId)
    {
        [$roles,$institutionIds]=$this->scopedContext($request, ['PRINCIPAL','ACCOUNTS','HR']);
        $item=DB::table('tagore_payroll_items as pi')->join('tagore_payroll_runs as r','r.id','=','pi.payroll_run_id')->join('users as u','u.id','=','pi.employee_id')
            ->where('pi.id',$itemId)->whereIn('r.institution_id',$institutionIds)->first(['pi.*','r.month','r.status as run_status','u.name as employee_name','u.email']);
        abort_unless($item,404);
        return Pdf::loadView('tagore.payroll.payslip',['item'=>$item])->download(($item->payslip_no ?: 'payslip-'.$itemId).'.pdf');
    }

    private function scopedContext(Request $request, array $allowedRoles): array
    {
        [$roles] = $this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect($allowedRoles)->isNotEmpty(), 403);

        if ($roles->intersect(['OWNER', 'ACCOUNTS'])->isNotEmpty()) {
            $institutionIds = DB::table('tagore_institutions')->where('status', 'active')->whereNull('deleted_at')
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            return [$roles, $institutionIds];
        }

        $institutionIds = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', (int) $request->user()->id)
            ->where('ur.status', 'active')
            ->whereIn('r.code', $allowedRoles)
            ->whereNotNull('ur.institution_id')
            ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
            ->pluck('ur.institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        return [$roles, $institutionIds];
    }

    private function context(Request $request): array
    {
        $userId=(int)$request->user()->id;
        $roles=DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')
            ->where(fn($query)=>$query->whereNull('ur.start_date')->orWhereDate('ur.start_date','<=',today()))
            ->where(fn($query)=>$query->whereNull('ur.end_date')->orWhereDate('ur.end_date','>=',today()))
            ->pluck('r.code')->unique()->values();
        $institutionIds=$roles->intersect(['OWNER','ACCOUNTS'])->isNotEmpty()
            ? DB::table('tagore_institutions')->where('status','active')->whereNull('deleted_at')->pluck('id')->map(fn($id)=>(int)$id)->all()
            : DB::table('tagore_user_roles as ur')->where('ur.user_id',$userId)->where('ur.status','active')->whereNotNull('ur.institution_id')
                ->where(fn($query)=>$query->whereNull('ur.start_date')->orWhereDate('ur.start_date','<=',today()))
                ->where(fn($query)=>$query->whereNull('ur.end_date')->orWhereDate('ur.end_date','>=',today()))
                ->pluck('ur.institution_id')->map(fn($id)=>(int)$id)->unique()->values()->all();
        return [$roles,$institutionIds];
    }
}
