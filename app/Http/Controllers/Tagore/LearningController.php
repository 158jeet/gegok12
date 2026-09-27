<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LearningController extends Controller
{
    public function index(Request $request): View
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','TEACHER','HOSTEL','ACCOUNTS'])->isNotEmpty(),403);
        $institutions=DB::table('tagore_institutions')->whereIn('id',$ids)->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        $rooms=DB::table('tagore_hostel_rooms')->whereIn('institution_id',$ids)->orderBy('hostel_name')->orderBy('room_no')->get();
        $courses=DB::table('tagore_course_records')->whereIn('institution_id',$ids)->orderBy('title')->get();
        $content=DB::table('tagore_content_records')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(100)->get();
        $surveys=DB::table('tagore_surveys')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(100)->get();
        $products=DB::table('tagore_store_products')->whereIn('institution_id',$ids)->orderBy('name')->get();
        return view('tagore.learning.index',compact('institutions','rooms','courses','content','surveys','products'));
    }

    public function room(Request $request)
    {
        [$roles,$ids]=$this->context($request); abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','HOSTEL'])->isNotEmpty(),403);
        $d=$request->validate(['institution_id'=>'required|integer','hostel_name'=>'required|string|max:100','room_no'=>'required|string|max:30','beds'=>'required|integer|min:1','fee'=>'numeric|min:0']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_hostel_rooms')->insert($d+['status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Hostel room created.');
    }

    public function allocate(Request $request)
    {
        [$roles,$ids]=$this->context($request); abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','HOSTEL'])->isNotEmpty(),403);
        $d=$request->validate(['room_id'=>'required|integer','student_id'=>'required|integer','bed_no'=>'required|string|max:30','check_in'=>'required|date']);
        $room=DB::table('tagore_hostel_rooms')->where('id',$d['room_id'])->first(); abort_unless($room && in_array((int)$room->institution_id,$ids,true),403);
        abort_unless(DB::table('users')->where('id',$d['student_id'])->where('usergroup_id',6)->whereNull('deleted_at')->exists(),422,'Student not found.');
        abort_if(DB::table('tagore_hostel_allocations')->where('room_id',$room->id)->where('bed_no',$d['bed_no'])->where('status','active')->exists(),422,'That bed is already occupied.');
        DB::table('tagore_hostel_allocations')->insert($d+['status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Student allocated to hostel bed.');
    }

    public function createCourse(Request $request)
    {
        [$roles,$ids]=$this->context($request); abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','TEACHER'])->isNotEmpty(),403);
        $d=$request->validate(['institution_id'=>'required|integer','title'=>'required|string|max:190','code'=>'nullable|string|max:50','description'=>'nullable|string|max:5000','grade_range'=>'nullable|string|max:100','capacity'=>'integer|min:0','fee'=>'numeric|min:0']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_course_records')->insert($d+['status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Course created.');
    }

    public function addContent(Request $request)
    {
        [$roles,$ids]=$this->context($request); abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','TEACHER'])->isNotEmpty(),403);
        $d=$request->validate(['institution_id'=>'required|integer','title'=>'required|string|max:190','content_type'=>'required|string|max:50','path'=>'nullable|string|max:1000','grade_range'=>'nullable|string|max:100','subject'=>'nullable|string|max:100','access_json'=>'nullable|json']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_content_records')->insert($d+['status'=>'published','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Learning content published.');
    }

    public function createSurvey(Request $request)
    {
        [$roles,$ids]=$this->context($request); abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','TEACHER'])->isNotEmpty(),403);
        $d=$request->validate(['institution_id'=>'required|integer','title'=>'required|string|max:190','questions_json'=>'required|json','anonymous'=>'boolean']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_surveys')->insert(['institution_id'=>$d['institution_id'],'title'=>$d['title'],'questions_json'=>$d['questions_json'],'anonymous'=>(bool)($d['anonymous']??false),'status'=>'published','published_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Survey published.');
    }

    public function respond(Request $request,int $surveyId)
    {
        $survey=DB::table('tagore_surveys')->where('id',$surveyId)->where('status','published')->first(); abort_unless($survey,404);
        $d=$request->validate(['answers_json'=>'required|json']);
        DB::table('tagore_survey_responses')->insert(['survey_id'=>$surveyId,'user_id'=>$survey->anonymous?null:$request->user()->id,'answers_json'=>$d['answers_json'],'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['ok'=>true]);
    }

    public function createProduct(Request $request)
    {
        [$roles,$ids]=$this->context($request); abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);
        $d=$request->validate(['institution_id'=>'required|integer','name'=>'required|string|max:190','sku'=>'required|string|max:100','category'=>'nullable|string|max:100','price'=>'required|numeric|min:0','stock'=>'required|integer|min:0']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_store_products')->insert($d+['status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Store product added.');
    }

    public function enroll(Request $request)
    {
        $d=$request->validate(['course_id'=>'required|integer','student_id'=>'required|integer']);
        $course=DB::table('tagore_course_records')->where('id',$d['course_id'])->first(); abort_unless($course && $course->status==='active',404);
        $isSelf=(int)$request->user()->id===(int)$d['student_id'];
        if(!$isSelf){
            $roles=$this->roles($request);
            abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','TEACHER'])->isNotEmpty(),403);
        }
        abort_unless(DB::table('users')->where('id',$d['student_id'])->where('usergroup_id',6)->whereNull('deleted_at')->exists(),422,'Student not found.');
        DB::table('tagore_course_enrollments')->updateOrInsert(
            ['course_id'=>$d['course_id'],'student_id'=>$d['student_id']],
            ['enrolled_at'=>now(),'status'=>'active','updated_at'=>now(),'created_at'=>now()]
        );
        return response()->json(['ok'=>true]);
    }

    public function progress(Request $request)
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

    public function surveyResponse(Request $request,int $surveyId)
    {
        $survey=DB::table('tagore_surveys')->where('id',$surveyId)->where('status','published')->first(); abort_unless($survey,404);
        $d=$request->validate(['answers_json'=>'required|json']);
        DB::table('tagore_survey_responses')->insert(['survey_id'=>$surveyId,'user_id'=>$survey->anonymous?null:$request->user()->id,'answers_json'=>$d['answers_json'],'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['ok'=>true]);
    }

    public function order(Request $request)
    {
        $d=$request->validate(['institution_id'=>'required|integer','items_json'=>'required|json']);
        $items=json_decode($d['items_json'],true); abort_unless(is_array($items)&&$items,422);
        $institution=DB::table('tagore_institutions')->where('id',$d['institution_id'])->where('status','active')->first(); abort_unless($institution,403);
        [$roles,$ids]=$this->context($request);
        abort_unless(in_array((int)$d['institution_id'],$ids,true) || $roles->contains('PARENT') || $roles->contains('STUDENT'),403);
        $total=0;
        DB::transaction(function()use($d,$items,$request,&$total){
            $rows=[];
            foreach($items as $line){
                $product=DB::table('tagore_store_products')->where('id',(int)($line['product_id']??0))->where('institution_id',$d['institution_id'])->lockForUpdate()->first();
                $qty=(int)($line['quantity']??0); abort_unless($product && $qty>0 && $qty <= $product->stock,422,'Invalid or unavailable store product.');
                $lineTotal=$qty*(float)$product->price; $total += $lineTotal;
                $rows[]=[$product,$qty,$lineTotal];
            }
            $orderId=DB::table('tagore_store_orders')->insertGetId(['institution_id'=>$d['institution_id'],'buyer_user_id'=>$request->user()->id,'total_amount'=>round($total,2),'status'=>'pending','payment_status'=>'unpaid','created_at'=>now(),'updated_at'=>now()]);
            foreach($rows as [$product,$qty,$lineTotal]){
                DB::table('tagore_store_order_items')->insert(['order_id'=>$orderId,'product_id'=>$product->id,'quantity'=>$qty,'unit_price'=>$product->price,'line_total'=>$lineTotal,'created_at'=>now(),'updated_at'=>now()]);
                DB::table('tagore_store_products')->where('id',$product->id)->update(['stock'=>$product->stock-$qty,'updated_at'=>now()]);
            }
        });
        return response()->json(['ok'=>true,'total'=>$total]);
    }

    private function roles(Request $request)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$request->user()->id)->where('ur.status','active')->pluck('r.code')->unique()->values();
    }

    private function context(Request $request): array
    {
        $userId=(int)$request->user()->id;
        $roles=DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();
        $ids=$roles->contains('OWNER') ? DB::table('tagore_institutions')->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all() : DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')->whereNotNull('institution_id')->pluck('institution_id')->map(fn($id)=>(int)$id)->unique()->values()->all();
        return [$roles,$ids];
    }
}
