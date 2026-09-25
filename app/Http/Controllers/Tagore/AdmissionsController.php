<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Models\TagoreAdmissionActivity;
use App\Models\TagoreAdmissionLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdmissionsController extends Controller
{
    private const STAFF_ROLES = ['OWNER','PRINCIPAL','COORDINATOR'];

    public function index(Request $request): View
    {
        $userId=(int)$request->user()->id; $roles=$this->roles($userId);
        abort_unless($roles->intersect(self::STAFF_ROLES)->isNotEmpty(),403);
        $ids=$this->institutionIds($userId,$roles);

        $query=TagoreAdmissionLead::query()->whereIn('institution_id',$ids)
            ->orderByRaw("case when status='new' then 0 when status='follow_up' then 1 else 2 end")
            ->orderBy('next_follow_up_at')->orderByDesc('id');

        if($request->filled('status')) $query->where('status',$request->string('status')->toString());
        if($request->filled('q')){
            $needle=trim($request->string('q')->toString());
            $query->where(fn($q)=>$q->where('student_name','like',"%{$needle}%")
                ->orWhere('parent_name','like',"%{$needle}%")->orWhere('mobile','like',"%{$needle}%"));
        }

        $leads=$query->paginate(25)->withQueryString();
        $base=TagoreAdmissionLead::whereIn('institution_id',$ids);
        $stats=[
            'total'=>(clone $base)->count(),
            'new'=>(clone $base)->where('status','new')->count(),
            'follow_up'=>(clone $base)->where('status','follow_up')->count(),
            'due_today'=>(clone $base)->whereBetween('next_follow_up_at',[now()->startOfDay(),now()->endOfDay()])->count(),
        ];
        return view('tagore.admissions.index',compact('leads','stats'));
    }

    public function create(Request $request): View
    {
        $userId=(int)$request->user()->id; $roles=$this->roles($userId);
        abort_unless($roles->intersect(self::STAFF_ROLES)->isNotEmpty(),403);
        $ids=$this->institutionIds($userId,$roles);
        $institutions=DB::table('tagore_institutions')->whereIn('id',$ids)->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        return view('tagore.admissions.create',compact('institutions'));
    }

    public function store(Request $request)
    {
        $userId=(int)$request->user()->id; $roles=$this->roles($userId);
        abort_unless($roles->intersect(self::STAFF_ROLES)->isNotEmpty(),403);
        $data=$request->validate([
            'institution_id'=>['required','integer'],'academic_year_id'=>['nullable','integer'],
            'student_name'=>['required','string','max:150'],'parent_name'=>['nullable','string','max:150'],
            'mobile'=>['nullable','string','max:30'],'alternate_mobile'=>['nullable','string','max:30'],
            'email'=>['nullable','email','max:190'],'class_name'=>['nullable','string','max:80'],
            'source'=>['nullable','string','max:60'],'assigned_to'=>['nullable','integer'],
            'next_follow_up_at'=>['nullable','date'],'notes'=>['nullable','string','max:5000'],
        ]);
        abort_unless(in_array((int)$data['institution_id'],$this->institutionIds($userId,$roles),true),403);

        $lead=DB::transaction(function() use($data,$userId){
            $institutionId=(int)$data['institution_id'];
            $next=(int)TagoreAdmissionLead::where('institution_id',$institutionId)->max('id')+1;
            $lead=TagoreAdmissionLead::create($data+['lead_no'=>sprintf('ADM-%s-%06d',$institutionId,$next),'status'=>'new']);
            TagoreAdmissionActivity::create(['lead_id'=>$lead->id,'user_id'=>$userId,'type'=>'created','notes'=>'Lead created','completed_at'=>now()]);
            return $lead;
        });
        return redirect()->route('tagore.admissions.show',$lead)->with('success','Admission lead created.');
    }

    public function show(Request $request,int $leadId): View
    {
        $userId=(int)$request->user()->id; $roles=$this->roles($userId);
        abort_unless($roles->intersect(self::STAFF_ROLES)->isNotEmpty(),403);
        $lead=TagoreAdmissionLead::with('activities')->findOrFail($leadId);
        abort_unless(in_array((int)$lead->institution_id,$this->institutionIds($userId,$roles),true),403);
        return view('tagore.admissions.show',compact('lead'));
    }

    public function activity(Request $request,int $leadId)
    {
        $userId=(int)$request->user()->id; $roles=$this->roles($userId);
        abort_unless($roles->intersect(self::STAFF_ROLES)->isNotEmpty(),403);
        $lead=TagoreAdmissionLead::findOrFail($leadId);
        abort_unless(in_array((int)$lead->institution_id,$this->institutionIds($userId,$roles),true),403);
        $data=$request->validate([
            'type'=>['required','in:call,whatsapp,meeting,note,visit'],'outcome'=>['nullable','string','max:80'],
            'notes'=>['nullable','string','max:5000'],'scheduled_at'=>['nullable','date'],'completed_at'=>['nullable','date'],
            'status'=>['nullable','in:new,contacted,follow_up,qualified,admitted,lost'],'next_follow_up_at'=>['nullable','date'],
        ]);
        DB::transaction(function() use($lead,$data,$userId){
            TagoreAdmissionActivity::create([
                'lead_id'=>$lead->id,'user_id'=>$userId,'type'=>$data['type'],'outcome'=>$data['outcome']??null,
                'notes'=>$data['notes']??null,'scheduled_at'=>$data['scheduled_at']??null,'completed_at'=>$data['completed_at']??null,
            ]);
            $updates=[]; if(!empty($data['status'])) $updates['status']=$data['status'];
            if(array_key_exists('next_follow_up_at',$data)) $updates['next_follow_up_at']=$data['next_follow_up_at'];
            if($updates) $lead->update($updates);
        });
        return back()->with('success','Activity saved.');
    }

    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')
            ->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();
    }

    private function institutionIds(int $userId,$roles): array
    {
        if($roles->contains('OWNER')) return DB::table('tagore_institutions')->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all();
        return DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')->whereNotNull('institution_id')
            ->pluck('institution_id')->map(fn($id)=>(int)$id)->unique()->values()->all();
    }
}
