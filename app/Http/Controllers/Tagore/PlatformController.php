<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class PlatformController extends Controller
{
    public function index(Request $request): View
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','ACCOUNTS','TEACHER'])->isNotEmpty(),403);
        $institutions=DB::table('tagore_institutions')->whereIn('id',$ids)->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        $alumni=DB::table('tagore_alumni_profiles')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(100)->get();
        $events=DB::table('tagore_alumni_events')->whereIn('institution_id',$ids)->orderByDesc('starts_at')->limit(50)->get();
        $plans=DB::table('tagore_fee_finance_plans')->whereIn('institution_id',$ids)->where('status','active')->get();
        $pages=DB::table('tagore_cms_pages')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(100)->get();
        $posts=DB::table('tagore_social_posts')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(100)->get();
        $rules=DB::table('tagore_automation_rules')->whereIn('institution_id',$ids)->orderBy('name')->get();
        $integrations=DB::table('tagore_integration_configs')->whereIn('institution_id',$ids)->orderBy('name')->get();
        $creatives=DB::table('tagore_creative_templates')->whereIn('institution_id',$ids)->orderBy('name')->get();
        return view('tagore.platform.index',compact('institutions','alumni','events','plans','pages','posts','rules','integrations','creatives','roles'));
    }

    public function alumni(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->guard($roles,['PRINCIPAL','COORDINATOR']);
        $d=$request->validate(['institution_id'=>'required|integer','student_id'=>'required|integer','passout_year'=>'nullable|integer','course'=>'nullable|string|max:100','phone'=>'nullable|string|max:30','email'=>'nullable|email|max:190','current_org'=>'nullable|string|max:190','designation'=>'nullable|string|max:190','notes'=>'nullable|string|max:4000']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_alumni_profiles')->updateOrInsert(['institution_id'=>$d['institution_id'],'student_id'=>$d['student_id']],$d+['created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Alumni profile saved.');
    }

    public function event(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->guard($roles,['PRINCIPAL','COORDINATOR']);
        $d=$request->validate(['institution_id'=>'required|integer','title'=>'required|string|max:190','starts_at'=>'required|date','ends_at'=>'nullable|date|after:starts_at','description'=>'nullable|string|max:4000']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_alumni_events')->insert($d+['status'=>'planned','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Alumni event created.');
    }

    public function financePlan(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->guard($roles,['PRINCIPAL','ACCOUNTS']);
        $d=$request->validate(['institution_id'=>'required|integer','name'=>'required|string|max:190','amount'=>'required|numeric|min:0','tenure_months'=>'required|integer|min:1','interest_rate'=>'numeric|min:0','processing_fee'=>'numeric|min:0','provider'=>'nullable|string|max:190']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_fee_finance_plans')->insert($d+['status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Fee financing plan created.');
    }

    public function applyFinance(Request $request)
    {
        $d=$request->validate(['plan_id'=>'required|integer','student_id'=>'required|integer']);
        $plan=DB::table('tagore_fee_finance_plans')->where('id',$d['plan_id'])->where('status','active')->first(); abort_unless($plan,404);
        $student=DB::table('users')->where('id',$d['student_id'])->where('usergroup_id',6)->whereNull('deleted_at')->first(); abort_unless($student,422,'Student not found.');
        $roles=$this->roles($request);
        if (!$roles->contains('OWNER') && !$roles->intersect(['PRINCIPAL','ACCOUNTS','COORDINATOR'])->isNotEmpty()) {
            if ($roles->contains('STUDENT')) abort_unless((int)$request->user()->id===(int)$student->id,403);
            elseif ($roles->contains('PARENT')) abort_unless(DB::table('tagore_parent_students')->where('parent_user_id',$request->user()->id)->where('student_id',$student->id)->where('status','active')->exists(),403);
            else abort(403);
        }
        $institutionSchool=DB::table('tagore_institutions')->where('id',$plan->institution_id)->value('school_id');
        abort_unless((int)$institutionSchool===(int)$student->school_id,403);
        $r=$plan->interest_rate/100/12; $n=(int)$plan->tenure_months; $p=(float)$plan->amount;
        $emi=$r>0 ? $p*$r*pow(1+$r,$n)/(pow(1+$r,$n)-1) : $p/$n;
        $id=DB::table('tagore_fee_finance_applications')->insertGetId(['plan_id'=>$plan->id,'student_id'=>$student->id,'principal'=>$p,'emi'=>round($emi,2),'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['ok'=>true,'application_id'=>$id,'emi'=>round($emi,2)]);
    }

    public function page(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->guard($roles,['PRINCIPAL','COORDINATOR']);
        $d=$request->validate(['institution_id'=>'required|integer','slug'=>'required|string|max:190','title'=>'required|string|max:190','body'=>'nullable|string','meta_title'=>'nullable|string|max:190','meta_description'=>'nullable|string|max:1000','status'=>'required|in:draft,published']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        $d['published_at']=$d['status']==='published'?now():null;
        DB::table('tagore_cms_pages')->updateOrInsert(['institution_id'=>$d['institution_id'],'slug'=>$d['slug']],$d+['created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Website page saved.');
    }

    public function post(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->guard($roles,['PRINCIPAL','COORDINATOR','TEACHER']);
        $d=$request->validate(['institution_id'=>'required|integer','group_name'=>'nullable|string|max:100','title'=>'nullable|string|max:190','body'=>'required|string|max:10000','attachment_path'=>'nullable|string|max:1000','status'=>'nullable|in:draft,published']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        $status=$d['status'] ?? 'draft';
        unset($d['status']);
        DB::table('tagore_social_posts')->insert($d+['author_id'=>$request->user()->id,'status'=>$status,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success',$status==='published' ? 'Internal social post published to the ERP feed.' : 'Social post draft saved. External publishing is not connected.');
    }

    public function aiTestPlan(Request $request)
    {
        [$roles,$ids]=$this->context($request);
        $this->guard($roles,['PRINCIPAL','COORDINATOR']);
        $d=$request->validate([
            'module'=>'required|in:tasks,olympiad,admissions,fees,social,learning',
            'role'=>'required|in:owner,principal,coordinator,teacher,parent,student,accounts,fee-editor',
            'test_type'=>'required|in:functional,workflow,accessibility,responsive,security',
            'scenario'=>'required|string|max:3000',
        ]);

        $url=trim((string)config('services.ollama.url'));
        $model=trim((string)config('services.ollama.model'));
        if ($url==='' || $model==='') {
            return back()->with('ai_test_plan_status','AI test-plan generation is not enabled yet. Configure OLLAMA_URL to an approved private model host; this ERP server has limited RAM and will not run a model locally.');
        }

        $prompt = "Act as a senior QA engineer for a school ERP. Produce a concise, safe, executable test plan as JSON only. Schema: {\"title\":string,\"preconditions\":string[],\"test_cases\":[{\"name\":string,\"steps\":string[],\"expected\":string[],\"severity\":\"low\"|\"medium\"|\"high\"}],\"risks\":string[]}. Do not include credentials or real student data. Do not recommend destructive actions against production. Module: {$d['module']}; role: {$d['role']}; test type: {$d['test_type']}; scenario: {$d['scenario']}";
        try {
            $response=Http::acceptJson()->timeout(40)->post(rtrim($url,'/').'/api/generate',[
                'model'=>$model,'prompt'=>$prompt,'stream'=>false,'format'=>'json',
                'options'=>['temperature'=>0.2],
            ]);
            if (!$response->successful()) {
                return back()->with('ai_test_plan_status','The configured AI provider returned HTTP '.$response->status().'. Check the private model service and retry.');
            }
            $raw=$response->json('response');
            $plan=is_string($raw) ? json_decode($raw,true) : null;
            if (!is_array($plan) || !isset($plan['test_cases']) || !is_array($plan['test_cases'])) {
                return back()->with('ai_test_plan_status','The AI provider response was not valid test-plan JSON. Retry with a narrower scenario.');
            }
            return back()->with('ai_test_plan',$plan);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('ai_test_plan_status','The AI provider could not be reached. No test was executed and no ERP data was changed.');
        }
    }

    /**
     * Read-only status check for a private Frigate NVR. This endpoint never proxies live streams or exposes API tokens.
     */
    public function cctvStatus(Request $request)
    {
        [$roles, $ids] = $this->context($request);
        $this->guard($roles, ['PRINCIPAL']);
        $respond = function (array $payload, int $httpStatus = 200) use ($request) {
            return $request->expectsJson()
                ? response()->json($payload, $httpStatus)
                : response()->view('tagore.platform.cctv-status', ['status' => $payload], $httpStatus);
        };

        $baseUrl = rtrim(trim((string) config('services.frigate.url')), '/');
        $token = trim((string) config('services.frigate.token'));
        if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return $respond([
                'configured' => false,
                'reachable' => false,
                'message' => 'CCTV analytics is not configured. Set FRIGATE_URL to the private Frigate service URL.',
            ], 503);
        }

        try {
            $client = Http::acceptJson()->timeout(5)->connectTimeout(3);
            if ($token !== '') {
                $client = $client->withToken($token);
            }
            $response = $client->get($baseUrl . '/api/stats');
            if (!$response->successful() || !is_array($response->json())) {
                return $respond([
                    'configured' => true,
                    'reachable' => false,
                    'message' => 'Frigate did not return a valid stats response. Check its private URL, token and service health.',
                ], 502);
            }

            $stats = $response->json();
            $cameras = is_array($stats['cameras'] ?? null) ? $stats['cameras'] : [];
            $detectors = is_array($stats['detectors'] ?? null) ? $stats['detectors'] : [];
            return $respond([
                'configured' => true,
                'reachable' => true,
                'camera_count' => count($cameras),
                'detector_count' => count($detectors),
                'camera_names' => array_slice(array_values(array_filter(array_map('strval', array_keys($cameras)))), 0, 100),
                'message' => 'Private Frigate service is reachable. This check does not verify each camera stream or AI detection accuracy.',
            ]);
        } catch (\Throwable $e) {
            report($e);
            return $respond([
                'configured' => true,
                'reachable' => false,
                'message' => 'Frigate could not be reached from the ERP app. Check the private network, firewall and service status.',
            ], 502);
        }
    }

    public function comment(Request $request,int $postId)
    {
        [$roles,$ids]=$this->context($request);
        $this->guard($roles,['PRINCIPAL','COORDINATOR','TEACHER']);
        $d=$request->validate(['body'=>'required|string|max:4000']);
        $post=DB::table('tagore_social_posts')->where('id',$postId)->whereIn('institution_id',$ids)->first();
        abort_unless($post,404);
        DB::table('tagore_social_comments')->insert(['post_id'=>$postId,'author_id'=>$request->user()->id,'body'=>$d['body'],'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['ok'=>true]);
    }

    public function automation(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->guard($roles,['PRINCIPAL','COORDINATOR']);
        $d=$request->validate(['institution_id'=>'required|integer','name'=>'required|string|max:190','trigger'=>'required|string|max:100','action'=>'required|string|max:100','config_json'=>'nullable|json','schedule'=>'nullable|string|max:100']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_automation_rules')->insert($d+['enabled'=>true,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Automation rule saved.');
    }

    public function integration(Request $request)
    {
        [$roles,$ids]=$this->context($request); abort_unless($roles->contains('OWNER'),403);
        $d=$request->validate(['institution_id'=>'required|integer','name'=>'required|string|max:190','provider'=>'required|string|max:100','type'=>'required|string|max:50','config_json'=>'nullable|json']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_integration_configs')->insert($d+['status'=>'configured','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Integration configuration saved.');
    }

    public function creative(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->guard($roles,['PRINCIPAL','COORDINATOR']);
        $d=$request->validate(['institution_id'=>'required|integer','name'=>'required|string|max:190','template_type'=>'required|string|max:80','template_json'=>'nullable|json','brand_json'=>'nullable|json']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_creative_templates')->insert($d+['status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Creative template saved.');
    }

    private function roles(Request $request)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$request->user()->id)->where('ur.status','active')->pluck('r.code')->unique()->values();
    }

    private function guard($roles,array $allowed): void { abort_unless($roles->contains('OWNER') || $roles->intersect($allowed)->isNotEmpty(),403); }
    private function context(Request $request): array
    {
        $userId=(int)$request->user()->id;
        $roles=DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();
        $ids=$roles->contains('OWNER') ? DB::table('tagore_institutions')->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all() : DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')->whereNotNull('institution_id')->pluck('institution_id')->map(fn($id)=>(int)$id)->unique()->values()->all();
        return [$roles,$ids];
    }
}
