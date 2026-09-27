<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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

    private const TABLES = [
        'payroll'=>'payroll','inventory'=>'inventory','expense'=>'expense','transport'=>'transport','hostel'=>'hostel',
        'alumni'=>'alumni','visitor'=>'visitor','gatepass'=>'gatepass','survey'=>'survey','notification'=>'notification',
        'document'=>'document','course'=>'course','content'=>'content','report'=>'report','social'=>'social',
        'website'=>'website','store'=>'store','fee-plan'=>'fee_plan','integration'=>'integration',
        'automation'=>'automation','creative'=>'creative',
    ];

    public function index(Request $request): View
    {
        $roles = $this->roles((int) $request->user()->id);
        $allowed = array_filter(
            self::MODULES,
            fn ($label, $key) => $roles->contains('OWNER') || $roles->intersect(self::ACCESS[$key] ?? [])->isNotEmpty(),
            ARRAY_FILTER_USE_BOTH
        );

        $module = (string) $request->query('module', array_key_first($allowed));
        abort_unless(isset($allowed[$module]), 403);

        $institutionIds = $this->institutionIds($request, $roles);
        $institutionId = $this->selectedInstitutionId($request, $roles, $institutionIds);
        $table = $this->table($module);

        $rows = DB::table($table)
            ->whereIn('institution_id', $institutionIds ?: [-1])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $institutions = DB::table('tagore_institutions')
            ->whereIn('id', $institutionIds ?: [-1])
            ->where('status', 'active')
            ->orderBy('display_name')
            ->get(['id','display_name']);

        return view('tagore.operations.index', [
            'modules'=>$allowed,
            'columns'=>self::COLUMNS[$module],
            'module'=>$module,
            'moduleTitle'=>self::MODULES[$module],
            'rows'=>$rows,
            'roles'=>$roles,
            'institutions'=>$institutions,
            'institutionId'=>$institutionId,
        ]);
    }

    public function store(Request $request, string $module)
    {
        $this->authorizeModule($request, $module);
        $roles = $this->roles((int) $request->user()->id);
        $institutionIds = $this->institutionIds($request, $roles);
        $institutionId = $this->selectedInstitutionId($request, $roles, $institutionIds);
        $data = $this->validated($request, $module);
        $data['institution_id'] = $institutionId;

        $id = DB::table($this->table($module))->insertGetId($data + ['created_at'=>now(),'updated_at'=>now()]);
        $this->audit($request, $module, $id, 'created', null, $data);

        return back()->with('success', self::MODULES[$module].' record created (#'.$id.').');
    }

    public function update(Request $request, string $module, int $id)
    {
        $this->authorizeModule($request, $module);
        $roles = $this->roles((int) $request->user()->id);
        $institutionIds = $this->institutionIds($request, $roles);
        $row = DB::table($this->table($module))->where('id',$id)->first();
        abort_unless($row,404);
        abort_unless(in_array((int) $row->institution_id, $institutionIds, true),403);

        $data = $this->validated($request, $module);
        DB::table($this->table($module))->where('id',$id)->update($data+['updated_at'=>now()]);
        $this->audit($request, $module, $id, 'updated', $row, $data);

        return back()->with('success','Record updated.');
    }

    public function destroy(Request $request, string $module, int $id)
    {
        $this->authorizeModule($request, $module);
        $roles = $this->roles((int) $request->user()->id);
        $institutionIds = $this->institutionIds($request, $roles);
        $row = DB::table($this->table($module))->where('id',$id)->first();
        abort_unless($row,404);
        abort_unless(in_array((int) $row->institution_id, $institutionIds, true),403);

        DB::table($this->table($module))->where('id',$id)->delete();
        $this->audit($request, $module, $id, 'deleted', $row, null);

        return back()->with('success','Record deleted.');
    }

    private function validated(Request $request, string $module): array
    {
        abort_unless(isset(self::COLUMNS[$module]),404);
        $out = [];

        foreach (self::COLUMNS[$module] as $column) {
            if (!$request->has($column)) {
                continue;
            }

            $value = $request->input($column);
            if (str_ends_with($column,'_json')) {
                $decoded = is_array($value) ? $value : json_decode((string) $value, true);
                abort_unless(is_array($decoded) && json_last_error() === JSON_ERROR_NONE || is_array($value), 422, 'Invalid JSON field: '.$column);
                $value = $decoded;
            }
            $out[$column] = $value;
        }

        return $out;
    }

    private function authorizeModule(Request $request, string $module): void
    {
        abort_unless(isset(self::MODULES[$module]),404);
        $roles = $this->roles((int) $request->user()->id);
        abort_unless($roles->contains('OWNER') || $roles->intersect(self::ACCESS[$module] ?? [])->isNotEmpty(),403);
    }

    private function institutionIds(Request $request, $roles): array
    {
        if ($roles->contains('OWNER')) {
            return DB::table('tagore_institutions')->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all();
        }

        return DB::table('tagore_user_roles')
            ->where('user_id',$request->user()->id)
            ->where('status','active')
            ->whereNotNull('institution_id')
            ->pluck('institution_id')
            ->map(fn($id)=>(int)$id)
            ->unique()
            ->values()
            ->all();
    }

    private function selectedInstitutionId(Request $request, $roles, array $institutionIds): int
    {
        $requested = (int) $request->input('institution_id');
        if ($roles->contains('OWNER')) {
            abort_unless($requested > 0 && in_array($requested, $institutionIds, true), 403);
            return $requested;
        }

        abort_unless(count($institutionIds) > 0, 403);
        if ($requested > 0) {
            abort_unless(in_array($requested, $institutionIds, true), 403);
            return $requested;
        }

        return (int) $institutionIds[0];
    }

    private function table(string $module): string
    {
        return 'tagore_'.self::TABLES[$module].'_records';
    }

    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r','r.id','=','ur.role_id')
            ->where('ur.user_id',$userId)
            ->where('ur.status','active')
            ->pluck('r.code')
            ->unique()
            ->values();
    }

    private function audit(Request $request, string $module, int $id, string $action, $old, $new): void
    {
        if (!DB::getSchemaBuilder()->hasTable('tagore_audit_events')) {
            return;
        }

        DB::table('tagore_audit_events')->insert([
            'user_id'=>(int)$request->user()->id,
            'institution_id'=>(int)$request->input('institution_id'),
            'action'=>'OPERATIONS_'.strtoupper($action),
            'entity_type'=>'tagore_'.$module.'_records',
            'entity_id'=>$id,
            'old_values_json'=>$old ? json_encode((array)$old) : null,
            'new_values_json'=>$new ? json_encode($new) : null,
            'ip_address'=>$request->ip(),
            'user_agent'=>substr((string)$request->userAgent(),0,2000),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
    }
}
