<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Models\TagoreAdmissionActivity;
use App\Models\TagoreAdmissionLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        if($request->filled('assigned_to')) $query->where('assigned_to',(int)$request->input('assigned_to'));
        if($request->input('follow_up')==='due') $query->whereNotNull('next_follow_up_at')->where('next_follow_up_at','<=',now());
        if($request->input('follow_up')==='overdue') $query->whereNotNull('next_follow_up_at')->where('next_follow_up_at','<',now());
        if($request->input('source')) $query->where('source',$request->string('source')->toString());
        if($request->input('campaign')) $query->where('campaign',$request->string('campaign')->toString());

        if($request->filled('q')){
            $needle=trim($request->string('q')->toString());
            $query->where(fn($q)=>$q->where('student_name','like',"%{$needle}%")
                ->orWhere('parent_name','like',"%{$needle}%")->orWhere('mobile','like',"%{$needle}%"));
        }

        $leads=$query->paginate(25)->withQueryString();
        $base=TagoreAdmissionLead::whereIn('institution_id',$ids);
        $staff=DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->join('users as u','u.id','=','ur.user_id')->whereIn('ur.institution_id',$ids)->where('ur.status','active')->whereIn('r.code',['OWNER','PRINCIPAL','COORDINATOR','TEACHER'])->whereNull('u.deleted_at')->select('u.id','u.name')->distinct()->orderBy('u.name')->get();
        $sources=(clone $base)->whereNotNull('source')->select('source',DB::raw('count(*) as total'))->groupBy('source')->orderByDesc('total')->limit(10)->get();
        $campaigns=(clone $base)->whereNotNull('campaign')->select('campaign',DB::raw('count(*) as total'))->groupBy('campaign')->orderByDesc('total')->limit(10)->get();
        $ownerPerformance=(clone $base)->whereNotNull('assigned_to')->select('assigned_to',DB::raw('count(*) as total'),DB::raw("sum(case when status='admitted' then 1 else 0 end) as admitted"),DB::raw("sum(case when status='lost' then 1 else 0 end) as lost"))->groupBy('assigned_to')->orderByDesc('admitted')->limit(10)->get();
        $ownerIds=$ownerPerformance->pluck('assigned_to')->map(fn($id)=>(int)$id)->all();
        $ownerNames=DB::table('users')->whereIn('id',$ownerIds)->pluck('name','id');
        $stats=[
            'total'=>(clone $base)->count(),
            'new'=>(clone $base)->where('status','new')->count(),
            'follow_up'=>(clone $base)->where('status','follow_up')->count(),
            'due_today'=>(clone $base)->whereBetween('next_follow_up_at',[now()->startOfDay(),now()->endOfDay()])->count(),
            'overdue'=>(clone $base)->whereNotNull('next_follow_up_at')->where('next_follow_up_at','<',now())->whereNotIn('status',['admitted','lost'])->count(),
            'qualified'=>(clone $base)->where('status','qualified')->count(),
            'admitted'=>(clone $base)->where('status','admitted')->count(),
            'lost'=>(clone $base)->where('status','lost')->count(),
            'conversion_rate'=>(($total=(clone $base)->count()) > 0 ? round(((clone $base)->where('status','admitted')->count() / $total) * 100,1) : 0),
        ];
        return view('tagore.admissions.index',compact('leads','stats','staff','sources','campaigns','ownerPerformance','ownerNames'));
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
            'source'=>['nullable','string','max:60'],'campaign'=>['nullable','string','max:100'],'assigned_to'=>['nullable','integer'],
            'next_follow_up_at'=>['nullable','date'],'notes'=>['nullable','string','max:5000'],
        ]);
        abort_unless(in_array((int)$data['institution_id'],$this->institutionIds($userId,$roles),true),403);
        if (!empty($data['assigned_to'])) abort_unless($this->staffBelongsToInstitution((int)$data['assigned_to'],(int)$data['institution_id']),422);

        $lead=DB::transaction(function() use($data,$userId){
            $institutionId=(int)$data['institution_id'];
            // Use the database-generated ID for the human-readable lead number.
            // This avoids max(id)+1 races when two admission staff create leads together.
            $lead=TagoreAdmissionLead::create($data+['lead_no'=>'PENDING-'.bin2hex(random_bytes(8)),'status'=>'new']);
            $lead->update(['lead_no'=>sprintf('ADM-%s-%06d',$institutionId,$lead->id)]);
            TagoreAdmissionActivity::create(['lead_id'=>$lead->id,'user_id'=>$userId,'type'=>'created','notes'=>'Lead created','completed_at'=>now()]);
            return $lead;
        });
        return redirect()->route('tagore.admissions.show',$lead)->with('success','Admission lead created.');
    }

    public function assign(Request $request,int $leadId)
    {
        $userId=(int)$request->user()->id; $roles=$this->roles($userId);
        abort_unless($roles->intersect(self::STAFF_ROLES)->isNotEmpty(),403);
        $lead=TagoreAdmissionLead::findOrFail($leadId);
        abort_unless(in_array((int)$lead->institution_id,$this->institutionIds($userId,$roles),true),403);
        $data=$request->validate(['assigned_to'=>['nullable','integer']]);
        if (!empty($data['assigned_to'])) abort_unless($this->staffBelongsToInstitution((int)$data['assigned_to'],(int)$lead->institution_id),422);
        $lead->update(['assigned_to'=>$data['assigned_to'] ?? null]);
        TagoreAdmissionActivity::create(['lead_id'=>$lead->id,'user_id'=>$userId,'type'=>'note','outcome'=>'assignment','notes'=>$lead->assigned_to ? 'Lead assigned to user #'.$lead->assigned_to : 'Lead unassigned','completed_at'=>now()]);
        return back()->with('success','Lead assignment updated.');
    }

    public function show(Request $request,int $leadId): View
    {
        $userId=(int)$request->user()->id; $roles=$this->roles($userId);
        abort_unless($roles->intersect(self::STAFF_ROLES)->isNotEmpty(),403);
        $lead=TagoreAdmissionLead::with('activities')->findOrFail($leadId);
        abort_unless(in_array((int)$lead->institution_id,$this->institutionIds($userId,$roles),true),403);
        $institution=DB::table('tagore_institutions')->where('id',$lead->institution_id)->first();
        $classOptions=DB::table('standards_link as sl')->join('standards as st','st.id','=','sl.standard_id')->join('sections as sec','sec.id','=','sl.section_id')->join('academic_years as ay','ay.id','=','sl.academic_year_id')->where('sl.school_id',$institution->school_id)->where('sl.status',1)->whereNull('sl.deleted_at')->orderByDesc('ay.start_date')->orderBy('st.name')->orderBy('sec.name')->select('sl.id','sl.academic_year_id','ay.name as academic_year_name','st.name as standard_name','sec.name as section_name')->get();
        $conversion=DB::table('tagore_admission_conversions')->where('lead_id',$lead->id)->first();
        $staff=DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->join('users as u','u.id','=','ur.user_id')->where('ur.institution_id',$lead->institution_id)->where('ur.status','active')->whereIn('r.code',['OWNER','PRINCIPAL','COORDINATOR','TEACHER'])->whereNull('u.deleted_at')->select('u.id','u.name')->distinct()->orderBy('u.name')->get();
        return view('tagore.admissions.show',compact('lead','staff','classOptions','conversion'));
    }

    public function linkFamily(Request $request, int $leadId)
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        abort_unless($roles->intersect(self::STAFF_ROLES)->isNotEmpty(), 403);
        $lead = TagoreAdmissionLead::findOrFail($leadId);
        abort_unless(in_array((int) $lead->institution_id, $this->institutionIds($userId, $roles), true), 403);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'parent_mobile' => ['required', 'string', 'max:30'],
            'relationship' => ['required', 'in:Mother,Father,Guardian,Other'],
        ]);

        $institution = DB::table('tagore_institutions')->where('id', $lead->institution_id)->first();
        abort_unless($institution, 422, 'Institution not found.');
        $student = DB::table('users')->where('id', (int) $data['student_id'])
            ->where('usergroup_id', 6)->where('school_id', $institution->school_id)->whereNull('deleted_at')->first();
        abort_unless($student, 422, 'Select a student account belonging to this institution.');

        $mobile = preg_replace('/[^0-9+]/', '', $data['parent_mobile']);
        // Parent identity is group-wide, but mobile matching alone is not proof of ownership.
        // Only a single account with a verified mobile may be linked.
        $matchingParents = DB::table('users')->where('usergroup_id', 7)
            ->where('mobile_no', $mobile)->where('mobile_verified', true)
            ->whereNull('deleted_at')->limit(2)->get();
        abort_unless($matchingParents->count() === 1, 422,
            $matchingParents->isEmpty()
                ? 'No verified parent account matches this mobile number. Verify the parent mobile number first.'
                : 'More than one verified parent account matches this mobile number. Resolve the duplicate accounts before linking.');
        $parent = $matchingParents->first();

        DB::transaction(function () use ($parent, $student, $data, $institution, $userId) {
            DB::table('tagore_parent_students')->updateOrInsert(
                ['parent_user_id' => $parent->id, 'student_id' => $student->id],
                ['relationship' => $data['relationship'], 'is_primary' => true, 'is_guardian' => true,
                 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]
            );
            if (DB::getSchemaBuilder()->hasTable('student_parent_links')) {
                DB::table('student_parent_links')->updateOrInsert(
                    ['parent_id' => $parent->id, 'student_id' => $student->id],
                    ['school_id' => $institution->school_id, 'status' => 1, 'updated_at' => now(), 'created_at' => now()]
                );
            }
            if (DB::getSchemaBuilder()->hasTable('tagore_audit_events')) {
                DB::table('tagore_audit_events')->insert([
                    'user_id' => $userId, 'action' => 'admissions.family_link',
                    'entity_type' => 'Student', 'entity_id' => $student->id,
                    'old_values_json' => null,
                    'new_values_json' => json_encode(['parent_user_id' => $parent->id, 'student_id' => $student->id, 'relationship' => $data['relationship'], 'institution_id' => $institution->id]),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Verified parent-child link saved. All siblings linked to this same parent account will appear together in the family fee summary.');
    }


    /**
     * Convert an admitted lead into a real student and academic enrolment.
     * The conversion row is the durable idempotency key; parent accounts are
     * deliberately not inferred from an unverified lead mobile number.
     */
    public function convert(Request $request, int $leadId)
    {
        $userId=(int)$request->user()->id; $roles=$this->roles($userId);
        abort_unless($roles->intersect(self::STAFF_ROLES)->isNotEmpty(),403);
        $data=$request->validate(['standard_link_id'=>['required','integer']]);

        $initialPassword = Str::random(40);
        $result=DB::transaction(function() use($leadId,$userId,$roles,$data,$initialPassword) {
            $lead=TagoreAdmissionLead::whereKey($leadId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array((int)$lead->institution_id,$this->institutionIds($userId,$roles),true),403);
            $existing=DB::table('tagore_admission_conversions')->where('lead_id',$lead->id)->first();
            if($existing) return ['conversion' => $existing, 'created' => false];
            abort_unless($lead->status==='admitted',422,'Mark the lead as admitted before creating the student account.');

            $institution=DB::table('tagore_institutions')->where('id',$lead->institution_id)->where('status','active')->first();
            abort_unless($institution,422,'The admission institution is not active.');
            $link=DB::table('standards_link as sl')
                ->join('academic_years as ay','ay.id','=','sl.academic_year_id')
                ->where('sl.id',(int)$data['standard_link_id'])
                ->where('sl.school_id',$institution->school_id)
                ->where('sl.status',1)->whereNull('sl.deleted_at')
                ->where('ay.school_id',$institution->school_id)->whereNull('ay.deleted_at')
                ->select('sl.id','sl.academic_year_id')->first();
            abort_unless($link,422,'Select an active class and section belonging to this institution.');
            if($lead->academic_year_id) abort_unless((int)$lead->academic_year_id===(int)$link->academic_year_id,422,'The selected class does not match the lead academic year.');

            $now=now();
            $registration='TAG-'.strtoupper(Str::random(12));
            $studentId=DB::table('users')->insertGetId([
                'usergroup_id'=>6,'school_id'=>$institution->school_id,'name'=>$lead->student_name,
                'password'=>Hash::make($initialPassword),
                'email'=>null,'mobile_no'=>null,'registration_number'=>$registration,
                'email_verification_code'=>Str::random(40),'email_verified'=>false,
                'mobile_verified'=>false,'status'=>'active','created_at'=>$now,'updated_at'=>$now,
            ]);
            DB::table('userprofiles')->insert([
                'school_id'=>$institution->school_id,'user_id'=>$studentId,'usergroup_id'=>6,
                'firstname'=>$lead->student_name,'registration_number'=>$registration,
                'avatar'=>'uploads/images.jpg','status'=>'active','created_at'=>$now,'updated_at'=>$now,
            ]);
            DB::table('student_academics')->insert([
                'school_id'=>$institution->school_id,'academic_year_id'=>$link->academic_year_id,
                'user_id'=>$studentId,'standardLink_id'=>$link->id,
                'roll_number'=>$registration,'id_card_number'=>$registration,
                'created_at'=>$now,'updated_at'=>$now,
            ]);
            $studentRoleId=DB::table('tagore_roles')->where('code','STUDENT')->where('status','active')->value('id');
            abort_unless($studentRoleId,422,'The Tagore Student role is not configured. Run the Tagore role setup before converting admissions.');
            DB::table('tagore_user_roles')->updateOrInsert(
                ['user_id'=>$studentId,'role_id'=>$studentRoleId,'institution_id'=>$institution->id],
                ['status'=>'active','created_at'=>$now,'updated_at'=>$now]
            );
            $conversionId=DB::table('tagore_admission_conversions')->insertGetId([
                'lead_id'=>$lead->id,'institution_id'=>$institution->id,'student_id'=>$studentId,
                'academic_year_id'=>$link->academic_year_id,'standard_link_id'=>$link->id,
                'converted_by'=>$userId,'created_at'=>$now,'updated_at'=>$now,
            ]);
            $lead->update(['academic_year_id'=>$link->academic_year_id,'converted_at'=>$now,'status'=>'admitted']);
            TagoreAdmissionActivity::create([
                'lead_id'=>$lead->id,'user_id'=>$userId,'type'=>'note','outcome'=>'student_created',
                'notes'=>'Lead converted to student account #'.$studentId.' and enrolled in academic year #'.$link->academic_year_id.'. Parent account was not auto-linked; verified family linking is a separate step.',
                'completed_at'=>$now,
            ]);
            return ['conversion' => DB::table('tagore_admission_conversions')->where('id',$conversionId)->first(), 'created' => true];
        });

        $conversion = $result['conversion'];
        $student = DB::table('users')->where('id', $conversion->student_id)->first(['registration_number']);
        $response = back()->with('success', 'Admission converted. Student account ID: '.$conversion->student_id.'. Link a parent only after the parent mobile is verified.');

        // The initial password is shown once to the authorized admissions operator.
        // It is never written to logs, activity notes, or the database in plaintext.
        if ($result['created']) {
            $response->with('issued_student_credentials', [
                'student_name' => TagoreAdmissionLead::whereKey($leadId)->value('student_name'),
                'registration_number' => $student->registration_number,
                'initial_password' => $initialPassword,
                'message' => 'Share these credentials securely. This password is shown only once.',
            ]);
        }

        return $response;
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
            'lost_reason'=>['nullable','string','max:120'],
        ]);
        DB::transaction(function() use($lead,$data,$userId){
            TagoreAdmissionActivity::create([
                'lead_id'=>$lead->id,'user_id'=>$userId,'type'=>$data['type'],'outcome'=>$data['outcome']??null,
                'notes'=>$data['notes']??null,'scheduled_at'=>$data['scheduled_at']??null,'completed_at'=>$data['completed_at']??null,
            ]);
            $updates=[];
            if(!empty($data['status'])) {
                $updates['status']=$data['status'];
                if($data['status']==='lost') $updates['lost_reason']=$data['lost_reason']??null;
                if($data['status']!=='lost') $updates['lost_reason']=null;
            }
            if(array_key_exists('next_follow_up_at',$data)) $updates['next_follow_up_at']=$data['next_follow_up_at'];
            if(in_array($data['type'],['call','whatsapp','meeting','visit'],true)) $updates['last_contacted_at']=now();
            if($updates) $lead->update($updates);
        });
        return back()->with('success','Activity saved.');
    }

    private function staffBelongsToInstitution(int $userId,int $institutionId): bool
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.institution_id',$institutionId)->where('ur.status','active')->whereIn('r.code',['OWNER','PRINCIPAL','COORDINATOR','TEACHER'])->exists();
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
