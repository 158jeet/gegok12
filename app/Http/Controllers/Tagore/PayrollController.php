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
        [$roles,$institutionIds] = $this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','ACCOUNTS','HR'])->isNotEmpty(),403);

        $runs=DB::table('tagore_payroll_runs')->whereIn('institution_id',$institutionIds)->orderByDesc('month')->orderByDesc('id')->limit(100)->get();
        $institutions=DB::table('tagore_institutions')->whereIn('id',$institutionIds)->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        $employees=DB::table('users')->whereIn('school_id',DB::table('tagore_institutions')->whereIn('id',$institutionIds)->pluck('school_id'))
            ->where('usergroup_id','!=',6)->whereNull('deleted_at')->orderBy('name')->limit(500)->get(['id','name','email']);
        return view('tagore.payroll.index',compact('runs','institutions','employees','institutionIds'));
    }

    public function generate(Request $request, PayrollService $service)
    {
        [$roles,$institutionIds] = $this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','ACCOUNTS','HR'])->isNotEmpty(),403);
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
        [$roles,$institutionIds]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);
        $run=DB::table('tagore_payroll_runs')->where('id',$runId)->first();
        abort_unless($run && in_array((int)$run->institution_id,$institutionIds,true),404);
        $service->approve($runId,(int)$request->user()->id);
        return back()->with('success','Payroll approved.');
    }

    public function markPaid(Request $request,int $runId,PayrollService $service)
    {
        [$roles,$institutionIds]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['ACCOUNTS'])->isNotEmpty(),403);
        $run=DB::table('tagore_payroll_runs')->where('id',$runId)->first();
        abort_unless($run && in_array((int)$run->institution_id,$institutionIds,true),404);
        $service->markPaid($runId);
        return back()->with('success','Payroll marked paid.');
    }

    public function payslip(Request $request,int $itemId)
    {
        [$roles,$institutionIds]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','ACCOUNTS','HR'])->isNotEmpty(),403);
        $item=DB::table('tagore_payroll_items as pi')->join('tagore_payroll_runs as r','r.id','=','pi.payroll_run_id')->join('users as u','u.id','=','pi.employee_id')
            ->where('pi.id',$itemId)->whereIn('r.institution_id',$institutionIds)->first(['pi.*','r.month','r.status as run_status','u.name as employee_name','u.email']);
        abort_unless($item,404);
        return Pdf::loadView('tagore.payroll.payslip',['item'=>$item])->download(($item->payslip_no ?: 'payslip-'.$itemId).'.pdf');
    }

    private function context(Request $request): array
    {
        $userId=(int)$request->user()->id;
        $roles=DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();
        $institutionIds=$roles->contains('OWNER')
            ? DB::table('tagore_institutions')->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all()
            : DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')->whereNotNull('institution_id')->pluck('institution_id')->map(fn($id)=>(int)$id)->unique()->values()->all();
        return [$roles,$institutionIds];
    }
}
