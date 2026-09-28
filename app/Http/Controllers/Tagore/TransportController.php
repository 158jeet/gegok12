<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TransportController extends Controller
{
    public function index(Request $request): View
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','TRANSPORT'])->isNotEmpty(),403);
        $institutions=DB::table('tagore_institutions')->whereIn('id',$ids)->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        $routes=DB::table('tagore_transport_routes')->whereIn('institution_id',$ids)->orderBy('name')->get();
        $vehicles=DB::table('tagore_transport_vehicles')->whereIn('institution_id',$ids)->orderBy('registration_no')->get();
        $drivers=DB::table('tagore_transport_drivers as d')->leftJoin('users as u','u.id','=','d.user_id')->whereIn('d.institution_id',$ids)->orderBy('u.name')->get(['d.*','u.name as driver_name']);
        $trips=DB::table('tagore_transport_trips as t')->join('tagore_transport_routes as r','r.id','=','t.route_id')->join('tagore_transport_vehicles as v','v.id','=','t.vehicle_id')->whereIn('t.institution_id',$ids)->orderByDesc('t.id')->limit(100)->get(['t.*','r.name as route_name','v.registration_no']);
        return view('tagore.transport.index',compact('institutions','routes','vehicles','drivers','trips'));
    }

    public function route(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->authorizeModule($roles);
        $d=$request->validate(['institution_id'=>'required|integer','name'=>'required|string|max:190','route_code'=>'nullable|string|max:80','academic_year_id'=>'nullable|integer','stops_json'=>'nullable|json','estimated_minutes'=>'nullable|integer|min:0','fee'=>'nullable|numeric|min:0']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        $routeCode=$d['route_code'] ?? 'R-'.Str::upper(Str::random(8));
        DB::table('tagore_transport_routes')->insert([
            'institution_id'=>(int)$d['institution_id'],
            'academic_year_id'=>$d['academic_year_id'] ?? null,
            'route_code'=>$routeCode,
            'route_name'=>$d['name'],
            'annual_amount'=>(float)($d['fee'] ?? 0),
            'name'=>$d['name'],
            'stops_json'=>$d['stops_json'] ?? null,
            'estimated_minutes'=>(int)($d['estimated_minutes'] ?? 0),
            'fee'=>(float)($d['fee'] ?? 0),
            'status'=>'active','created_at'=>now(),'updated_at'=>now(),
        ]);
        return back()->with('success','Transport route created.');
    }

    public function vehicle(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->authorizeModule($roles);
        $d=$request->validate(['institution_id'=>'required|integer','registration_no'=>'required|string|max:50','vehicle_type'=>'nullable|string|max:50','capacity'=>'required|integer|min:1','gps_device_id'=>'nullable|string|max:100']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_transport_vehicles')->insert($d+['status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Vehicle added.');
    }

    public function driver(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->authorizeModule($roles);
        $d=$request->validate(['institution_id'=>'required|integer','user_id'=>'required|integer','license_no'=>'nullable|string|max:80','license_expiry'=>'nullable|date']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        abort_unless(DB::table('users')->where('id',$d['user_id'])->whereNull('deleted_at')->exists(),422,'Driver user was not found.');
        DB::table('tagore_transport_drivers')->insert($d+['status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Driver registered.');
    }

    public function startTrip(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->authorizeModule($roles);
        $d=$request->validate(['institution_id'=>'required|integer','route_id'=>'required|integer','vehicle_id'=>'required|integer','driver_id'=>'nullable|integer']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        abort_unless(DB::table('tagore_transport_routes')->where('id',$d['route_id'])->where('institution_id',$d['institution_id'])->exists(),422);
        abort_unless(DB::table('tagore_transport_vehicles')->where('id',$d['vehicle_id'])->where('institution_id',$d['institution_id'])->exists(),422);
        if (!empty($d['driver_id'])) abort_unless(DB::table('tagore_transport_drivers')->where('id',$d['driver_id'])->where('institution_id',$d['institution_id'])->exists(),422);
        $id=DB::table('tagore_transport_trips')->insertGetId($d+['status'=>'active','started_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Trip started (#'.$id.').');
    }

    public function gpsPing(Request $request,int $tripId)
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['TRANSPORT','PRINCIPAL'])->isNotEmpty(),403);
        $trip=DB::table('tagore_transport_trips')->where('id',$tripId)->whereIn('institution_id',$ids)->first(); abort_unless($trip,404);
        $d=$request->validate(['latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','speed_kmh'=>'nullable|numeric|min:0','accuracy_m'=>'nullable|numeric|min:0','recorded_at'=>'nullable|date']);
        DB::table('tagore_transport_gps_events')->insert($d+['trip_id'=>$tripId,'recorded_at'=>$d['recorded_at']??now(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['ok'=>true,'trip_id'=>$tripId,'recorded_at'=>$d['recorded_at']??now()]);
    }

    public function live(Request $request,int $tripId)
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['TRANSPORT','PRINCIPAL'])->isNotEmpty() || $roles->contains('PARENT'),403);
        $trip=DB::table('tagore_transport_trips')->where('id',$tripId)->first();
        abort_unless($trip,404);
        if ($roles->contains('PARENT') && !$roles->contains('OWNER') && !$roles->intersect(['TRANSPORT','PRINCIPAL'])->isNotEmpty()) {
            $studentIds=DB::table('tagore_parent_students')->where('parent_user_id',$request->user()->id)->where('status','active')->pluck('student_id');
            abort_unless(DB::table('tagore_transport_assignments')->where('route_id',$trip->route_id)->whereIn('student_id',$studentIds)->where('status','active')->exists(),403);
        } else {
            abort_unless(in_array((int)$trip->institution_id,$ids,true),403);
        }
        $gps=DB::table('tagore_transport_gps_events')->where('trip_id',$tripId)->orderByDesc('recorded_at')->first();
        return response()->json(['trip'=>$trip,'gps'=>$gps]);
    }

    public function boarding(Request $request,int $tripId)
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['TRANSPORT','PRINCIPAL'])->isNotEmpty(),403);
        $trip=DB::table('tagore_transport_trips')->where('id',$tripId)->whereIn('institution_id',$ids)->first(); abort_unless($trip,404);
        $d=$request->validate(['student_id'=>'required|integer','event_type'=>'required|in:boarded,deboarded','method'=>'nullable|string|max:30']);
        DB::table('tagore_transport_boardings')->insert($d+['trip_id'=>$tripId,'recorded_by'=>$request->user()->id,'recorded_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['ok'=>true]);
    }

    private function authorizeModule($roles): void { abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','TRANSPORT'])->isNotEmpty(),403); }

    private function context(Request $request): array
    {
        $userId=(int)$request->user()->id;
        $roles=DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();
        $ids=$roles->contains('OWNER') ? DB::table('tagore_institutions')->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all() : DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')->whereNotNull('institution_id')->pluck('institution_id')->map(fn($id)=>(int)$id)->unique()->values()->all();
        return [$roles,$ids];
    }
}
