<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\StreamedResponse;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    private const SOURCES = [
        'payroll_runs'=>['table'=>'tagore_payroll_runs','columns'=>['id','institution_id','month','status','gross_total','deduction_total','net_total']],
        'payroll_items'=>['table'=>'tagore_payroll_items','columns'=>['id','payroll_run_id','employee_id','basic_salary','gross_amount','pf_amount','esi_amount','tds_amount','other_deductions','net_amount','status']],
        'inventory'=>['table'=>'tagore_inventory_items','columns'=>['id','institution_id','name','sku','category','unit','quantity','reorder_level','unit_cost','status']],
        'expenses'=>['table'=>'tagore_expense_claims','columns'=>['id','institution_id','expense_date','department','category','description','amount','vendor','status']],
        'assets'=>['table'=>'tagore_assets','columns'=>['id','institution_id','asset_tag','name','category','purchase_date','cost','department','assigned_to','status']],
        'transport_trips'=>['table'=>'tagore_transport_trips','columns'=>['id','institution_id','route_id','vehicle_id','driver_id','started_at','ended_at','status']],
        'communications'=>['table'=>'tagore_message_campaigns','columns'=>['id','institution_id','title','channel','scheduled_at','status','sent_count','failed_count']],
    ];

    public function index(Request $request): View
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty(),403);
        $institutions=DB::table('tagore_institutions')->whereIn('id',$ids)->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        $reports=DB::table('tagore_report_records')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(100)->get();
        return view('tagore.reports.index',compact('institutions','reports'));
    }

    public function save(Request $request)
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty(),403);
        $d=$request->validate(['institution_id'=>'required|integer','name'=>'required|string|max:190','source'=>'required|string','columns'=>'required|array|min:1','format'=>'required|in:table,csv,pdf']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        abort_unless(isset(self::SOURCES[$d['source']]),422,'Unknown report source.');
        abort_unless(count(array_diff($d['columns'],self::SOURCES[$d['source']]['columns']))===0,422,'Invalid report column.');
        $id=DB::table('tagore_report_records')->insertGetId([
            'institution_id'=>$d['institution_id'],'name'=>$d['name'],'module'=>$d['source'],
            'definition_json'=>json_encode(['source'=>$d['source'],'columns'=>$d['columns']]),
            'format'=>$d['format'],'status'=>'active','created_at'=>now(),'updated_at'=>now()
        ]);
        return back()->with('success','Report definition saved (#'.$id.').');
    }

    public function export(Request $request,int $id)
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty(),403);
        $report=DB::table('tagore_report_records')->where('id',$id)->whereIn('institution_id',$ids)->first();
        abort_unless($report,404);
        $definition=json_decode((string)$report->definition_json,true) ?: [];
        $source=$definition['source']??null; $columns=$definition['columns']??[];
        abort_unless(isset(self::SOURCES[$source]) && $columns,422);
        $query=DB::table(self::SOURCES[$source]['table'])->where('institution_id',$report->institution_id)->select($columns)->limit(5000);
        $rows=$query->get();

        if($request->string('format')->toString()==='pdf'){
            return Pdf::loadView('tagore.reports.pdf',['report'=>$report,'columns'=>$columns,'rows'=>$rows])->download('tagore-report-'.$id.'.pdf');
        }

        return new StreamedResponse(function() use($rows,$columns){
            $out=fopen('php://output','w'); fputcsv($out,$columns);
            foreach($rows as $row) fputcsv($out,array_map(fn($column)=>is_array($row->{$column}??null)?json_encode($row->{$column}):($row->{$column}??''),$columns));
            fclose($out);
        },200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="tagore-report.csv"']);
    }

    private function context(Request $request): array
    {
        $userId=(int)$request->user()->id;
        $roles=DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();
        $ids=$roles->contains('OWNER') ? DB::table('tagore_institutions')->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all() : DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')->whereNotNull('institution_id')->pluck('institution_id')->map(fn($id)=>(int)$id)->unique()->values()->all();
        return [$roles,$ids];
    }
}
