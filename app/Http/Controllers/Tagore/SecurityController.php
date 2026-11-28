<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function index(Request $request): View
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR'])->isNotEmpty(),403);
        $institutions=DB::table('tagore_institutions')->whereIn('id',$ids)->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        $visitors=DB::table('tagore_security_visitors')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(100)->get();
        $passes=DB::table('tagore_security_gatepasses')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(100)->get();
        $events=DB::table('tagore_security_events')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(100)->get();
        return view('tagore.security.index',compact('institutions','visitors','passes','events'));
    }

    public function visitor(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->authorizeModule($roles);
        $d=$request->validate(['institution_id'=>'required|integer','visitor_name'=>'required|string|max:190','phone'=>'nullable|string|max:30','purpose'=>'required|string|max:190','host_user_id'=>'nullable|integer']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        $institution=DB::table('tagore_institutions')->where('id',(int)$d['institution_id'])->where('status','active')->first(['school_id']);
        abort_unless($institution,403);
        if (!empty($d['host_user_id'])) {
            $hostExists=DB::table('users')->where('id',(int)$d['host_user_id'])->where('school_id',$institution->school_id)
                ->whereIn('usergroup_id',[5,8,10,11,12,13])->where('status','active')->whereNull('deleted_at')->exists();
            abort_unless($hostExists,422,'The visitor host must be an active staff member of the selected institution.');
        }        $otp=(string)random_int(100000,999999);
        $id=DB::table('tagore_security_visitors')->insertGetId($d+['otp_hash'=>hash('sha256',$otp),'otp_expires_at'=>now()->addMinutes(15),'status'=>'expected','created_at'=>now(),'updated_at'=>now()]);
        $this->event($request,(int)$d['institution_id'],'VISITOR_CREATED','tagore_security_visitors',$id,['otp_issued'=>true]);
        return back()->with('success','Visitor created (#'.$id.'). OTP: '.$otp.' — give this to the visitor or send through your configured communication channel.');
    }

    public function checkIn(Request $request,int $id)
    {
        [$roles,$ids]=$this->context($request); $this->authorizeModule($roles);
        $d=$request->validate(['otp'=>'required|digits:6']);
        $visitor=DB::table('tagore_security_visitors')->where('id',$id)->whereIn('institution_id',$ids)->first(); abort_unless($visitor,404);
        abort_unless($visitor->status==='expected' && $visitor->otp_expires_at && now()->lt($visitor->otp_expires_at),422,'Visitor OTP has expired or visitor is already processed.');
        abort_unless(hash_equals((string)$visitor->otp_hash,hash('sha256',$d['otp'])),422,'Invalid visitor OTP.');
        DB::table('tagore_security_visitors')->where('id',$id)->update(['status'=>'checked_in','checked_in_at'=>now(),'updated_at'=>now()]);
        $this->event($request,(int)$visitor->institution_id,'VISITOR_CHECKED_IN','tagore_security_visitors',$id);
        return back()->with('success','Visitor checked in.');
    }

    public function checkOut(Request $request,int $id)
    {
        [$roles,$ids]=$this->context($request); $this->authorizeModule($roles);
        $visitor=DB::table('tagore_security_visitors')->where('id',$id)->whereIn('institution_id',$ids)->first(); abort_unless($visitor,404);
        abort_unless($visitor->status==='checked_in',422,'Visitor is not checked in.');
        DB::table('tagore_security_visitors')->where('id',$id)->update(['status'=>'checked_out','checked_out_at'=>now(),'updated_at'=>now()]);
        $this->event($request,(int)$visitor->institution_id,'VISITOR_CHECKED_OUT','tagore_security_visitors',$id);
        return back()->with('success','Visitor checked out.');
    }

    public function gatepass(Request $request)
    {
        [$roles,$ids]=$this->context($request, ['PRINCIPAL','COORDINATOR','TEACHER']);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','TEACHER'])->isNotEmpty(),403);
        $d=$request->validate(['institution_id'=>'required|integer','student_id'=>'required|integer','pass_type'=>'required|string|max:50','reason'=>'required|string|max:2000','valid_from'=>'required|date','valid_until'=>'required|date|after:valid_from']);
        $institution=DB::table('tagore_institutions')
            ->where('id',(int)$d['institution_id'])
            ->whereIn('id',$ids)
            ->where('status','active')
            ->first(['id','school_id']);
        abort_unless($institution,403);
        $student=DB::table('users')
            ->where('id',$d['student_id'])
            ->where('school_id',$institution->school_id)
            ->whereNull('deleted_at')
            ->where('usergroup_id',6)->where('status','active')
            ->first();
        abort_unless($student,422,'Student not found in the selected institution.');
        $token=Str::random(64);
        $id=DB::table('tagore_security_gatepasses')->insertGetId($d+['token_hash'=>hash('sha256',$token),'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        $this->event($request,(int)$d['institution_id'],'GATEPASS_REQUESTED','tagore_security_gatepasses',$id);
        return back()->with('success','Gate pass requested (#'.$id.'). Token: '.$token);
    }

    public function decidePass(Request $request,int $id)
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR'])->isNotEmpty(),403);
        $d=$request->validate(['decision'=>'required|in:approved,rejected']);
        $pass=DB::table('tagore_security_gatepasses')->where('id',$id)->whereIn('institution_id',$ids)->first(); abort_unless($pass,404);
        abort_unless($pass->status==='pending',422,'Gate pass is already decided.');
        DB::table('tagore_security_gatepasses')->where('id',$id)->update(['status'=>$d['decision'],'approved_by'=>$request->user()->id,'updated_at'=>now()]);
        $this->event($request,(int)$pass->institution_id,'GATEPASS_'.strtoupper($d['decision']),'tagore_security_gatepasses',$id);
        return back()->with('success','Gate pass '.$d['decision'].'.');
    }

    public function verifyPass(Request $request,string $token)
    {
        $hash=hash('sha256',$token);
        $pass=DB::table('tagore_security_gatepasses as g')
            ->join('users as u','u.id','=','g.student_id')
            ->join('tagore_institutions as i','i.id','=','g.institution_id')
            ->where('g.token_hash',$hash)->where('i.status','active')
            ->first(['g.id','g.institution_id','g.student_id','g.pass_type','g.reason','g.valid_from','g.valid_until','g.status','g.verified_at','u.name as student_name','i.display_name as institution']);
        abort_unless($pass,404);

        $valid=$pass->status==='approved' && $pass->verified_at===null && now()->between($pass->valid_from,$pass->valid_until);
        if($valid) {
            $updated=DB::table('tagore_security_gatepasses')->where('id',$pass->id)->where('status','approved')->whereNull('verified_at')->update([
                'status'=>'used','verified_at'=>now(),'verified_by'=>$request->user()?->id,'updated_at'=>now()
            ]);
            $valid=$updated===1;
            if($valid){$pass->status='used';$pass->verified_at=now();}
        }
        $this->event($request,(int)$pass->institution_id,'GATEPASS_VERIFIED','tagore_security_gatepasses',(int)$pass->id,['valid'=>$valid,'single_use'=>true]);
        return response()->json([
            'valid' => $valid,
            'pass' => [
                'student_name' => $pass->student_name,
                'institution' => $pass->institution,
                'pass_type' => $pass->pass_type,
                'reason' => $pass->reason,
                'valid_from' => $pass->valid_from,
                'valid_until' => $pass->valid_until,
                'status' => $pass->status,
                'verified_at' => $pass->verified_at,
            ],
        ]);
    }
    private function authorizeModule($roles): void { abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR'])->isNotEmpty(),403); }

    private function event(Request $request,int $institutionId,string $type,string $subjectType,int $subjectId,array $meta=[]): void
    {
        DB::table('tagore_security_events')->insert([
            'institution_id'=>$institutionId,'actor_user_id'=>$request->user()?->id,'event_type'=>$type,
            'subject_type'=>$subjectType,'subject_id'=>$subjectId,'metadata_json'=>json_encode($meta),
            'ip_address'=>$request->ip(),'created_at'=>now(),'updated_at'=>now()
        ]);
    }

    private function context(Request $request, array $scopeRoles = ['PRINCIPAL', 'COORDINATOR']): array
    {
        $userId=(int)$request->user()->id;
        $roles=DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r','r.id','=','ur.role_id')
            ->where('ur.user_id',$userId)->where('ur.status','active')
            ->where(fn($query)=>$query->whereNull('ur.start_date')->orWhereDate('ur.start_date','<=',today()))
            ->where(fn($query)=>$query->whereNull('ur.end_date')->orWhereDate('ur.end_date','>=',today()))
            ->pluck('r.code')->unique()->values();

        if($roles->contains('OWNER')) {
            $ids=DB::table('tagore_institutions')->where('status','active')->whereNull('deleted_at')->pluck('id')->map(fn($id)=>(int)$id)->all();
            return [$roles,$ids];
        }

        $ids=DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r','r.id','=','ur.role_id')
            ->join('tagore_institutions as i','i.id','=','ur.institution_id')
            ->where('ur.user_id',$userId)->where('ur.status','active')
            ->whereIn('r.code',$scopeRoles)
            ->where('i.status','active')->whereNull('i.deleted_at')
            ->where(fn($query)=>$query->whereNull('ur.start_date')->orWhereDate('ur.start_date','<=',today()))
            ->where(fn($query)=>$query->whereNull('ur.end_date')->orWhereDate('ur.end_date','>=',today()))
            ->pluck('ur.institution_id')->map(fn($id)=>(int)$id)->unique()->values()->all();

        return [$roles,$ids];
    }
}
