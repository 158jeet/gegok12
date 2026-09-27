<?php

namespace App\\Http\\Controllers\\Tagore;

use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\View\\View;

class OperationsController extends Controller
{
    private const MODULES = [
        'payroll'=>'Payroll & HR','inventory'=>'Inventory & Assets','expense'=>'Expenses & Purchases',
        'transport'=>'Transport & GPS','hostel'=>'Hostel / Boarding','alumni'=>'Alumni',
        'visitor'=>'Visitors','gatepass'=>'Gate Pass','survey'=>'Feedback & Surveys',
        'notification'=>'Communication','document'=>'Documents','course'=>'Academic & Skill Courses',
        'content'=>'Learning Content','report'=>'Custom Reports','social'=>'Internal Social',
        'website'=>'Website CMS','store'=>'Student Store','fee-plan'=>'Fee Financing',
        'integration'=>'Integrations','automation'=>'Automation','creative'=>'Branded Creatives',
    ];

    private const ACCESS = [
        'payroll'=>['OWNER','PRINCIPAL'],'inventory'=>['OWNER','PRINCIPAL','ACCOUNTS'],'expense'=>['OWNER','PRINCIPAL','ACCOUNTS'],
        'transport'=>['OWNER','PRINCIPAL','TRANSPORT'],'hostel'=>['OWNER','PRINCIPAL','HOSTEL'],'alumni'=>['OWNER','PRINCIPAL','COORDINATOR'],
        'visitor'=>['OWNER','PRINCIPAL','COORDINATOR'],'gatepass'=>['OWNER','PRINCIPAL','COORDINATOR'],'survey'=>['OWNER','PRINCIPAL','COORDINATOR','TEACHER'],
        'notification'=>['OWNER','PRINCIPAL','COORDINATOR'],'document'=>['OWNER','PRINCIPAL','COORDINATOR','TEACHER','HR','ACCOUNTS'],
        'course'=>['OWNER','PRINCIPAL','COORDINATOR','TEACHER'],'content'=>['OWNER','PRINCIPAL','COORDINATOR','TEACHER'],
        'report'=>['OWNER','PRINCIPAL','COORDINATOR','ACCOUNTS'],'social'=>['OWNER','PRINCIPAL','COORDINATOR','TEACHER'],
        'website'=>['OWNER','PRINCIPAL','COORDINATOR'],'store'=>['OWNER','PRINCIPAL','ACCOUNTS'],'fee-plan'=>['OWNER','PRINCIPAL','ACCOUNTS'],
        'integration'=>['OWNER'],'automation'=>['OWNER','PRINCIPAL','COORDINATOR'],'creative'=>['OWNER','PRINCIPAL','COORDINATOR'],
    ];

    private const COLUMNS = [
        'payroll'=>['employee_id','month','gross_amount','deductions','net_amount','status'],
        'inventory'=>['item_name','category','sku','quantity','unit','reorder_level','unit_cost','vendor','status'],
        'expense'=>['expense_date','category','description','amount','vendor','status','approved_by'],
        'transport'=>['vehicle_no','route_name','driver_name','capacity','gps_device_id','status'],
        'hostel'=>['hostel_name','room_no','bed_no','student_id','check_in','check_out','status'],
        'alumni'=>['student_id','passout_year','course','phone','email','current_org','notes'],
        'visitor'=>['visitor_name','phone','purpose','host_name','check_in','check_out','otp','status'],
        'gatepass'=>['student_id','pass_type','reason','valid_from','valid_until','approved_by','status'],
        'survey'=>['title','audience','is_anonymous','questions_json','status','published_at'],
        'notification'=>['channel','audience','title','message','scheduled_at','status','sent_count','failed_count'],
        'document'=>['owner_type','owner_id','title','document_type','path','version','access_json','status'],
        'course'=>['title','code','description','grade_range','capacity','fee','status'],
        'content'=>['title','content_type','path','grade_range','subject','access_json','status'],
        'report'=>['name','module','definition_json','schedule','format','recipients_json','status'],
        'social'=>['author_id','post_type','title','body','group_name','attachment_path','status'],
        'website'=>['slug','title','body','meta_title','meta_description','published_at','status'],
        'store'=>['product_name','sku','category','price','stock','reorder_level','status'],
        'fee-plan'=>['name','description','amount','tenure','emi_amount','provider','status'],
        'integration'=>['name','provider','type','config_json','last_sync_at','status'],
        'automation'=>['name','module','trigger','action','schedule','last_run_at','next_run_at','status'],
        'creative'=>['name','template_type','template_json','brand_json','status'],
    ];

    public function index(Request $request): View
    {
        $roles=$this->roles((int)$request->user()->id);
        $module=$request->query('module','payroll');
        abort_unless(isset(self::MODULES[$module]),404);
        $table=$this->table($module);
        $rows=DB::table($table)->orderByDesc('id')->limit(200)->get();
        return view('tagore.operations.index',[
            'modules'=>self::MODULES,'columns'=>self::COLUMNS[$module],'module'=>$module,'moduleTitle'=>self::MODULES[$module],
            'rows'=>$rows,'roles'=>$roles,
        ]);
    }

    public function store(Request $request,string $module)
    {
        $this->authorizeModule($request,$module);
        $data=$this->validated($request,$module);
        $data['institution_id']=$this->institutionId($request);
        $id=DB::table($this->table($module))->insertGetId($data+['created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success',self::MODULES[$module].' record created (#'.$id.').');
    }

    public function update(Request $request,string $module,int $id)
    {
        $this->authorizeModule($request,$module);
        $data=$this->validated($request,$module);
        $row=DB::table($this->table($module))->where('id',$id)->first();
        abort_unless($row,404);
        $this->scope($request,$row);
        DB::table($this->table($module))->where('id',$id)->update($data+['updated_at'=>now()]);
        return back()->with('success','Record updated.');
    }

    public function destroy(Request $request,string $module,int $id)
    {
        $this->authorizeModule($request,$module);
        $row=DB::table($this->table($module))->where('id',$id)->first();
        abort_unless($row,404);
        $this->scope($request,$row);
        DB::table($this->table($module))->where('id',$id)->delete();
        return back()->with('success','Record deleted.');
    }

    private function validated(Request $request,string $module): array
    {
        abort_unless(isset(self::COLUMNS[$module]),404);
        $out=[];
        foreach(self::COLUMNS[$module] as $column){
            if($request->has($column)){
                $value=$request->input($column);
                if(str_ends_with($column,'_json')||in_array($column,['access_json','recipients_json','definition_json','config_json','template_json','brand_json','questions_json'])){
                    $decoded=is_array($value)?$value:json_decode((string)$value,true);
                    abort_unless(json_last_error()===JSON_ERROR_NONE || is_array($value),422,'Invalid JSON field: '.$column);
                    $value=$decoded;
                }
                $out[$column]=$value;
            }
        }
        return $out;
    }

    private function authorizeModule(Request $request,string $module): void
    {
        abort_unless(isset(self::MODULES[$module]),404);
        $roles=$this->roles((int)$request->user()->id);
        abort_unless($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','ACCOUNTS','HR','LIBRARY','TRANSPORT','HOSTEL','TEACHER'])->isNotEmpty(),403);
    }

    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();
    }

    private function institutionId(Request $request): ?int
    {
        $roles=$this->roles((int)$request->user()->id);
        if($roles->contains('OWNER')) return $request->integer('institution_id') ?: null;
        return DB::table('tagore_user_roles')->where('user_id',$request->user()->id)->where('status','active')->whereNotNull('institution_id')->value('institution_id');
    }

    private function scope(Request $request,object $row): void
    {
        $roles=$this->roles((int)$request->user()->id);
        if(!$roles->contains('OWNER') && (int)($row->institution_id??0)!==(int)$this->institutionId($request)) abort(403);
    }

    private function table(string $module): string { return 'tagore_'.$module.'_records'; }
}