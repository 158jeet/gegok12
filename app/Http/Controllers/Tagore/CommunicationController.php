<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\CommunicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR'])->isNotEmpty(),403);
        $institutions=DB::table('tagore_institutions')->whereIn('id',$ids)->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        $campaigns=DB::table('tagore_message_campaigns')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(100)->get();
        return view('tagore.communication.index',compact('institutions','campaigns'));
    }

    public function create(Request $request)
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR'])->isNotEmpty(),403);
        $d=$request->validate([
            'institution_id'=>'required|integer','title'=>'required|string|max:190',
            'channel'=>'required|in:email,sms,whatsapp,push','audience_json'=>'required|json',
            'message'=>'required|string|max:10000','scheduled_at'=>'nullable|date'
        ]);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        abort_unless(is_array(json_decode($d['audience_json'],true)),422,'Audience JSON must be an object/array.');
        $status=empty($d['scheduled_at'])?'queued':'scheduled';
        $id=DB::table('tagore_message_campaigns')->insertGetId([
            'institution_id'=>$d['institution_id'],'title'=>$d['title'],'channel'=>$d['channel'],
            'audience_json'=>$d['audience_json'],'message'=>$d['message'],'scheduled_at'=>$d['scheduled_at']??null,
            'status'=>$status,'created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()
        ]);
        if($status==='queued') app(CommunicationService::class)->sendCampaign($id);
        return back()->with('success','Communication campaign created (#'.$id.').');
    }

    public function send(Request $request,int $id,CommunicationService $service)
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR'])->isNotEmpty(),403);
        $campaign=DB::table('tagore_message_campaigns')->where('id',$id)->whereIn('institution_id',$ids)->first(); abort_unless($campaign,404);
        $result=$service->sendCampaign($id);
        return back()->with('success','Campaign processed: '.$result['sent'].' sent, '.$result['failed'].' failed.');
    }

    public function device(Request $request)
    {
        $d=$request->validate(['token'=>'required|string|max:500','platform'=>'nullable|string|max:30']);
        DB::table('tagore_user_devices')->updateOrInsert(
            ['user_id'=>$request->user()->id,'token'=>$d['token']],
            ['platform'=>$d['platform']??null,'last_seen_at'=>now(),'updated_at'=>now(),'created_at'=>now()]
        );
        return response()->json(['ok'=>true]);
    }

    private function context(Request $request): array
    {
        $userId=(int)$request->user()->id;
        $roles=DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();
        $ids=$roles->contains('OWNER') ? DB::table('tagore_institutions')->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all() : DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')->whereNotNull('institution_id')->pluck('institution_id')->map(fn($id)=>(int)$id)->unique()->values()->all();
        return [$roles,$ids];
    }
}
