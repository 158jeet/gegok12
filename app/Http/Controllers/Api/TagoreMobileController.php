<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TagoreMobileController extends Controller
{
    public function liveTransport(Request $request,int $tripId)
    {
        $roles=$this->roles($request);
        $trip=DB::table('tagore_transport_trips')->where('id',$tripId)->first();
        abort_unless($trip,404);

        if($roles->contains('PARENT')){
            $students=DB::table('tagore_parent_students')->where('parent_user_id',$request->user()->id)->where('status','active')->pluck('student_id');
            abort_unless(DB::table('tagore_transport_assignments')->where('route_id',$trip->route_id)->whereIn('student_id',$students)->where('status','active')->exists(),403);
        } elseif(!$roles->contains('OWNER') && !$roles->intersect(['PRINCIPAL','TRANSPORT'])->isNotEmpty()) {
            abort(403);
        }

        return response()->json([
            'trip'=>$trip,
            'gps'=>DB::table('tagore_transport_gps_events')->where('trip_id',$tripId)->orderByDesc('recorded_at')->first(),
            'recent_boardings'=>DB::table('tagore_transport_boardings')->where('trip_id',$tripId)->orderByDesc('recorded_at')->limit(50)->get(),
        ]);
    }

    public function gps(Request $request,int $tripId)
    {
        $roles=$this->roles($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['TRANSPORT','PRINCIPAL'])->isNotEmpty(),403);
        $trip=DB::table('tagore_transport_trips')->where('id',$tripId)->first(); abort_unless($trip,404);
        $d=$request->validate(['latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','speed_kmh'=>'nullable|numeric|min:0','accuracy_m'=>'nullable|numeric|min:0','recorded_at'=>'nullable|date']);
        DB::table('tagore_transport_gps_events')->insert($d+['trip_id'=>$tripId,'recorded_at'=>$d['recorded_at']??now(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['ok'=>true]);
    }

    public function boarding(Request $request,int $tripId)
    {
        $roles=$this->roles($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['TRANSPORT','PRINCIPAL'])->isNotEmpty(),403);
        $trip=DB::table('tagore_transport_trips')->where('id',$tripId)->first(); abort_unless($trip,404);
        $d=$request->validate(['student_id'=>'required|integer','event_type'=>'required|in:boarded,deboarded','method'=>'nullable|string|max:30']);
        abort_unless(DB::table('tagore_transport_assignments')->where('route_id',$trip->route_id)->where('student_id',$d['student_id'])->where('status','active')->exists(),422,'Student is not assigned to this route.');
        DB::table('tagore_transport_boardings')->insert($d+['trip_id'=>$tripId,'recorded_by'=>$request->user()->id,'recorded_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['ok'=>true]);
    }

    public function registerDevice(Request $request)
    {
        $d=$request->validate(['token'=>'required|string|max:500','platform'=>'nullable|string|max:30']);
        DB::table('tagore_user_devices')->updateOrInsert(
            ['user_id'=>$request->user()->id,'token'=>$d['token']],
            ['platform'=>$d['platform']??null,'last_seen_at'=>now(),'updated_at'=>now(),'created_at'=>now()]
        );
        return response()->json(['ok'=>true]);
    }

    public function courseProgress(Request $request)
    {
        $d=$request->validate(['course_id'=>'required|integer','content_id'=>'nullable|integer','progress'=>'required|numeric|between:0,100']);
        $studentId=(int)$request->user()->id;
        abort_unless(DB::table('tagore_course_enrollments')->where('course_id',$d['course_id'])->where('student_id',$studentId)->where('status','active')->exists(),403);
        DB::table('tagore_content_progress')->updateOrInsert(
            ['course_id'=>$d['course_id'],'student_id'=>$studentId,'content_id'=>$d['content_id']??null],
            ['progress'=>(float)$d['progress'],'last_accessed_at'=>now(),'updated_at'=>now(),'created_at'=>now()]
        );
        return response()->json(['ok'=>true]);
    }

    private function roles(Request $request)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$request->user()->id)->where('ur.status','active')->pluck('r.code')->unique()->values();
    }
}
