<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AcademicStructureController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->user()->id;
        $this->owner($userId);
        $institutions = DB::table('tagore_institutions')->where('status', 'active')->orderBy('display_name')->get(['id', 'display_name', 'school_id']);
        $years = DB::table('academic_years')->orderByDesc('start_date')->get(['id', 'school_id', 'name', 'start_date', 'end_date', 'status']);
        $streams = DB::table('tagore_academic_streams')->orderBy('name')->get(['id', 'institution_id', 'name', 'code', 'status']);
        $sections = DB::table('tagore_academic_sections as s')
            ->join('tagore_institutions as i', 'i.id', '=', 's.institution_id')
            ->join('academic_years as ay', 'ay.id', '=', 's.academic_year_id')
            ->join('standards_link as sl', 'sl.id', '=', 's.standard_link_id')
            ->leftJoin('tagore_academic_streams as st', 'st.id', '=', 's.stream_id')
            ->orderByDesc('ay.start_date')->orderBy('sl.id')->orderBy('s.code')
            ->limit(500)
            ->get(['s.id','s.name','s.code','s.status','i.display_name as institution','ay.name as academic_year','sl.id as standard_link_id','st.name as stream']);
        return view('tagore.academic-structure', compact('institutions','years','streams','sections'));
    }

    public function storeStream(Request $request)
    {
        $this->owner((int) $request->user()->id);
        $data = $request->validate(['institution_id'=>['required','integer','exists:tagore_institutions,id'],'name'=>['required','string','max:100'],'code'=>['required','string','max:50']]);
        $data['code'] = strtoupper(trim($data['code']));
        abort_if(DB::table('tagore_academic_streams')->where('institution_id',$data['institution_id'])->where('code',$data['code'])->exists(),422,'Stream code already exists for this institution.');
        DB::table('tagore_academic_streams')->insert([...$data,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Stream added.');
    }

    public function storeSection(Request $request)
    {
        $this->owner((int) $request->user()->id);
        $data = $request->validate(['institution_id'=>['required','integer','exists:tagore_institutions,id'],'academic_year_id'=>['required','integer','exists:academic_years,id'],'standard_link_id'=>['required','integer','exists:standards_link,id'],'stream_id'=>['nullable','integer','exists:tagore_academic_streams,id'],'name'=>['required','string','max:50'],'code'=>['required','string','max:50']]);
        $institution = DB::table('tagore_institutions')->where('id',$data['institution_id'])->first(['id','school_id']);
        $year = DB::table('academic_years')->where('id',$data['academic_year_id'])->first(['id','school_id']);
        abort_unless($institution && $year && (int)$institution->school_id === (int)$year->school_id,422,'Academic year must belong to the selected institution.');
        $standard = DB::table('standards_link')->where('id',$data['standard_link_id'])->first(['id','school_id']);
        abort_unless($standard && (int)$standard->school_id === (int)$institution->school_id,422,'Class must belong to the selected institution school.');
        if ($data['stream_id']) abort_unless(DB::table('tagore_academic_streams')->where('id',$data['stream_id'])->where('institution_id',$institution->id)->exists(),422,'Stream must belong to the selected institution.');
        $data['code'] = strtoupper(trim($data['code']));
        abort_if(DB::table('tagore_academic_sections')->where('academic_year_id',$data['academic_year_id'])->where('standard_link_id',$data['standard_link_id'])->where('code',$data['code'])->exists(),422,'Section code already exists for this class and academic year.');
        DB::table('tagore_academic_sections')->insert([...$data,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Section added.');
    }

    private function owner(int $userId): void
    {
        abort_unless(DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->where('r.code','OWNER')->exists(),403);
    }
}
