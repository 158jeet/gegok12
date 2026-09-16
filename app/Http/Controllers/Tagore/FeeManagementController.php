<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\FeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FeeManagementController extends Controller
{
    private const MANAGE_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'ACCOUNTS'];

    public function index(Request $request): View
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        abort_unless($roles->intersect(self::MANAGE_ROLES)->isNotEmpty(), 403);
        $institutions = $this->institutions($userId, $roles);
        $schoolIds = $institutions->pluck('school_id');
        $institutionIds = $institutions->pluck('id');

        $structures = DB::table('tagore_fee_structures as f')
            ->join('tagore_institutions as i', 'i.id', '=', 'f.institution_id')
            ->leftJoin('academic_years as ay', 'ay.id', '=', 'f.academic_year_id')
            ->whereIn('f.institution_id', $institutionIds)->orderByDesc('f.id')
            ->get(['f.*', 'i.display_name as institution', 'ay.name as academic_year']);

        $years = DB::table('academic_years')->whereIn('school_id', $schoolIds)->orderByDesc('start_date')->limit(20)->get();
        $classSections = DB::table('standards_link as sl')
            ->join('standards as st', 'st.id', '=', 'sl.standard_id')
            ->join('sections as sec', 'sec.id', '=', 'sl.section_id')
            ->join('schools as sc', 'sc.id', '=', 'sl.school_id')
            ->leftJoin('academic_years as ay', 'ay.id', '=', 'sl.academic_year_id')
            ->whereIn('sl.school_id', $schoolIds)->where('sl.status', true)->whereNull('sl.deleted_at')
            ->orderBy('sc.name')->orderBy('st.order')->orderBy('st.name')->orderBy('sec.name')
            ->limit(1000)->get(['sl.id', 'sl.school_id', 'sl.academic_year_id', 'sl.stream', 'st.name as standard', 'sec.name as section', 'sc.name as school', 'ay.name as academic_year']);

        $assignments = DB::table('tagore_fee_structure_assignments as a')
            ->join('tagore_fee_structures as f', 'f.id', '=', 'a.fee_structure_id')
            ->join('tagore_institutions as i', 'i.id', '=', 'a.institution_id')
            ->join('standards_link as sl', 'sl.id', '=', 'a.standard_link_id')
            ->join('standards as st', 'st.id', '=', 'sl.standard_id')
            ->join('sections as sec', 'sec.id', '=', 'sl.section_id')
            ->leftJoin('academic_years as ay', 'ay.id', '=', 'a.academic_year_id')
            ->whereIn('a.institution_id', $institutionIds)->where('a.status', 'active')
            ->orderByDesc('a.id')->limit(500)
            ->get(['a.*', 'f.name as fee_structure', 'i.display_name as institution', 'st.name as standard', 'sec.name as section', 'sl.stream', 'ay.name as academic_year']);

        return view('tagore.fees.manage', compact('institutions', 'structures', 'years', 'classSections', 'assignments', 'roles'));
    }

    public function storeStructure(Request $request)
    {
        $userId = (int) $request->user()->id; $roles = $this->roles($userId); $this->assertManager($roles);
        $data = $request->validate([
            'institution_id' => ['required','integer','exists:tagore_institutions,id'],
            'academic_year_id' => ['nullable','integer','exists:academic_years,id'],
            'name' => ['required','string','max:160'], 'description' => ['nullable','string','max:1000'],
            'frequency' => ['required','in:annual,term,monthly,one_time'], 'heads' => ['required','array','min:1'],
            'heads.*.name' => ['required','string','max:120'], 'heads.*.code' => ['required','string','max:60'],
            'heads.*.amount' => ['required','numeric','min:0'], 'heads.*.category' => ['nullable','string','max:60'],
            'heads.*.optional' => ['nullable','boolean'],
        ]);
        $this->authorizeInstitution($userId, (int) $data['institution_id'], $roles);
        DB::transaction(function () use ($data) {
            $structureId = DB::table('tagore_fee_structures')->insertGetId([
                'institution_id' => $data['institution_id'], 'academic_year_id' => $data['academic_year_id'] ?? null,
                'name' => $data['name'], 'description' => $data['description'] ?? null, 'frequency' => $data['frequency'],
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($data['heads'] as $head) DB::table('tagore_fee_components')->insert([
                'fee_structure_id' => $structureId, 'name' => $head['name'], 'code' => $head['code'], 'category' => $head['category'] ?? null,
                'amount' => round((float) $head['amount'], 2), 'tax_rate' => 0, 'is_optional' => (bool) ($head['optional'] ?? false),
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return back()->with('success', 'Fee structure created.');
    }

    public function generateDemand(Request $request, FeeService $feeService)
    {
        $userId = (int) $request->user()->id; $roles = $this->roles($userId); $this->assertManager($roles);
        $data = $request->validate(['student_id'=>['required','integer','exists:users,id'],'fee_structure_id'=>['required','integer','exists:tagore_fee_structures,id'],'due_date'=>['nullable','date'],'concession_amount'=>['nullable','numeric','min:0'],'discount_amount'=>['nullable','numeric','min:0']]);
        $structure = DB::table('tagore_fee_structures')->where('id', $data['fee_structure_id'])->first(); abort_unless($structure,404);
        $this->authorizeInstitution($userId, (int) $structure->institution_id, $roles);
        $student = DB::table('users')->where('id',$data['student_id'])->first(['id','school_id','usergroup_id']); abort_unless($student && (int)$student->usergroup_id===6,422);
        $institution = DB::table('tagore_institutions')->where('id',$structure->institution_id)->first(['id','school_id']); abort_unless($institution && (int)$institution->school_id===(int)$student->school_id,422);
        $components = DB::table('tagore_fee_components')->where('fee_structure_id',$structure->id)->where('status','active')->orderBy('id')->get();
        $items = $components->map(fn($c)=>['fee_component_id'=>$c->id,'fee_head'=>$c->name,'code'=>$c->code,'gross_amount'=>(float)$c->amount,'discount_amount'=>0,'concession_amount'=>0])->all();
        $total = round(array_sum(array_column($items,'gross_amount')),2); $discount=round((float)($data['discount_amount']??0),2); $concession=round((float)($data['concession_amount']??0),2); abort_unless($discount+$concession<=$total,422);
        if ($items) { $items[0]['discount_amount']=$discount; $items[0]['concession_amount']=$concession; }
        $id=$feeService->createObligation((int)$student->id,(int)$structure->institution_id,['academic_year_id'=>$structure->academic_year_id,'fee_structure_id'=>$structure->id,'due_date'=>$data['due_date']??null,'frequency'=>$structure->frequency,'items'=>$items,'gross_amount'=>$total,'discount_amount'=>$discount,'concession_amount'=>$concession],$userId);
        return back()->with('success', "Student demand #{$id} created.");
    }

    public function storeAssignment(Request $request)
    {
        $userId=(int)$request->user()->id; $roles=$this->roles($userId); $this->assertManager($roles);
        $data=$request->validate(['fee_structure_id'=>['required','integer','exists:tagore_fee_structures,id'],'standard_link_id'=>['required','integer','exists:standards_link,id']]);
        $structure=DB::table('tagore_fee_structures')->where('id',$data['fee_structure_id'])->first(); abort_unless($structure,404);
        $this->authorizeInstitution($userId,(int)$structure->institution_id,$roles);
        $link=DB::table('standards_link')->where('id',$data['standard_link_id'])->whereNull('deleted_at')->first(); abort_unless($link,422);
        $institution=DB::table('tagore_institutions')->where('id',$structure->institution_id)->first(['school_id']);
        abort_unless($institution && (int)$institution->school_id===(int)$link->school_id,422);
        if ($structure->academic_year_id) abort_unless((int)$structure->academic_year_id===(int)$link->academic_year_id,422);
        $this->authorizeClassScope($userId,(int)$data['standard_link_id'],$roles);
        DB::table('tagore_fee_structure_assignments')->updateOrInsert(
            ['fee_structure_id'=>$structure->id,'standard_link_id'=>$link->id],
            ['institution_id'=>$structure->institution_id,'academic_year_id'=>$link->academic_year_id,'status'=>'active','updated_at'=>now(),'created_at'=>now()]
        );
        return back()->with('success','Fee structure assigned to the selected class/section.');
    }

    public function bulkPreview(Request $request): View
    {
        $userId=(int)$request->user()->id; $roles=$this->roles($userId); $this->assertManager($roles);
        $data=$request->validate(['assignment_id'=>['required','integer','exists:tagore_fee_structure_assignments,id']]);
        $assignment=$this->assignmentForUser($userId,$roles,(int)$data['assignment_id']);
        $students=$this->eligibleStudents($assignment);
        $existing=DB::table('tagore_fee_obligations')->where('institution_id',$assignment->institution_id)->where('academic_year_id',$assignment->academic_year_id)->where('fee_structure_id',$assignment->fee_structure_id)->whereIn('student_id',$students->pluck('id'))->pluck('student_id');
        $preview=['assignment'=>$assignment,'eligible'=>$students,'existing'=>$existing,'new_count'=>$students->whereNotIn('id',$existing)->count()];
        $base=$this->index($request);
        return $base;
    }

    public function generateBulk(Request $request, FeeService $feeService)
    {
        $userId=(int)$request->user()->id; $roles=$this->roles($userId); $this->assertManager($roles);
        $data=$request->validate(['assignment_id'=>['required','integer','exists:tagore_fee_structure_assignments,id'],'due_date'=>['nullable','date']]);
        $assignment=$this->assignmentForUser($userId,$roles,(int)$data['assignment_id']);
        $structure=DB::table('tagore_fee_structures')->where('id',$assignment->fee_structure_id)->where('status','active')->first(); abort_unless($structure,422);
        $components=DB::table('tagore_fee_components')->where('fee_structure_id',$structure->id)->where('status','active')->orderBy('id')->get(); abort_unless($components->isNotEmpty(),422);
        $items=$components->map(fn($c)=>['fee_component_id'=>$c->id,'fee_head'=>$c->name,'code'=>$c->code,'gross_amount'=>(float)$c->amount,'discount_amount'=>0,'concession_amount'=>0])->all();
        $students=$this->eligibleStudents($assignment);
        $existing=DB::table('tagore_fee_obligations')->where('institution_id',$assignment->institution_id)->where('academic_year_id',$assignment->academic_year_id)->where('fee_structure_id',$assignment->fee_structure_id)->whereIn('student_id',$students->pluck('id'))->pluck('student_id')->all();
        $result=$feeService->createBulkDemands($students->whereNotIn('id',$existing)->pluck('id')->all(),(int)$assignment->institution_id,(int)$assignment->academic_year_id,(int)$structure->id,$structure->frequency,$items,(float)$components->sum('amount'),$data['due_date']??null,$userId);
        return back()->with('success',count($result['created']).' demands generated; '.count($result['skipped']).' already existed.');
    }

    private function eligibleStudents(object $assignment)
    {
        return DB::table('student_academics as sa')->join('users as u','u.id','=','sa.user_id')->where('sa.school_id',DB::table('tagore_institutions')->where('id',$assignment->institution_id)->value('school_id'))->where('sa.academic_year_id',$assignment->academic_year_id)->where('sa.standardLink_id',$assignment->standard_link_id)->where('u.usergroup_id',6)->whereNull('sa.deleted_at')->orderBy('u.name')->get(['u.id','u.name','u.school_id']);
    }

    private function assignmentForUser(int $userId,$roles,int $assignmentId): object
    {
        $a=DB::table('tagore_fee_structure_assignments')->where('id',$assignmentId)->where('status','active')->first(); abort_unless($a,404);
        $this->authorizeInstitution($userId,(int)$a->institution_id,$roles); $this->authorizeClassScope($userId,(int)$a->standard_link_id,$roles); return $a;
    }

    private function authorizeClassScope(int $userId,int $standardLinkId,$roles):void
    {
        if ($roles->contains('OWNER') || $roles->contains('PRINCIPAL') || $roles->contains('ACCOUNTS')) return;
        $hasClassScopes=DB::table('tagore_user_scopes')->where('user_id',$userId)->whereIn('scope_type',['standard_link','class','CLASS'])->exists();
        if ($hasClassScopes) abort_unless(DB::table('tagore_user_scopes')->where('user_id',$userId)->whereIn('scope_type',['standard_link','class','CLASS'])->where('scope_id',$standardLinkId)->exists(),403);
    }

    private function assertManager($roles):void { abort_unless($roles->intersect(self::MANAGE_ROLES)->isNotEmpty(),403); }
    private function roles(int $userId){return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();}
    private function institutions(int $userId,$roles){$q=DB::table('tagore_institutions as i')->join('schools as s','s.id','=','i.school_id')->where('i.status','active');if(!$roles->contains('OWNER'))$q->whereIn('i.id',DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')->whereNotNull('institution_id')->pluck('institution_id'));return $q->orderBy('i.display_name')->get(['i.id','i.display_name','i.school_id']);}
    private function authorizeInstitution(int $userId,int $institutionId,$roles):void{if($roles->contains('OWNER'))return;abort_unless(DB::table('tagore_user_roles')->where('user_id',$userId)->where('institution_id',$institutionId)->where('status','active')->exists(),403);}
}
