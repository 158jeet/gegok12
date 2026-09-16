<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\FeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FeeManagementController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'ACCOUNTS'])->isNotEmpty(), 403);
        $institutions = $this->institutions($userId, $roles);
        $ids = $institutions->pluck('id');
        $structures = DB::table('tagore_fee_structures as f')
            ->join('tagore_institutions as i', 'i.id', '=', 'f.institution_id')
            ->leftJoin('academic_years as ay', 'ay.id', '=', 'f.academic_year_id')
            ->whereIn('f.institution_id', $ids)
            ->orderByDesc('f.id')->get(['f.*', 'i.display_name as institution', 'ay.name as academic_year']);
        $students = DB::table('users as u')->whereIn('u.school_id', $institutions->pluck('school_id'))
            ->where('u.usergroup_id', 6)->orderBy('u.name')->limit(500)->get(['u.id','u.name','u.school_id']);
        $years = DB::table('academic_years')->whereIn('school_id', $institutions->pluck('school_id'))->orderByDesc('start_date')->limit(20)->get();
        return view('tagore.fees.manage', compact('institutions','structures','students','years','roles'));
    }

    public function storeStructure(Request $request)
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        abort_unless($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty(), 403);
        $data = $request->validate([
            'institution_id' => ['required','integer','exists:tagore_institutions,id'],
            'academic_year_id' => ['nullable','integer','exists:academic_years,id'],
            'name' => ['required','string','max:160'],
            'description' => ['nullable','string','max:1000'],
            'frequency' => ['required','in:annual,term,monthly,one_time'],
            'heads' => ['required','array','min:1'],
            'heads.*.name' => ['required','string','max:120'],
            'heads.*.code' => ['required','string','max:60'],
            'heads.*.amount' => ['required','numeric','min:0'],
            'heads.*.category' => ['nullable','string','max:60'],
            'heads.*.optional' => ['nullable','boolean'],
        ]);
        $this->authorizeInstitution($userId, (int) $data['institution_id'], $roles);
        DB::transaction(function () use ($data) {
            $structureId = DB::table('tagore_fee_structures')->insertGetId([
                'institution_id'=>$data['institution_id'],'academic_year_id'=>$data['academic_year_id'] ?? null,
                'name'=>$data['name'],'description'=>$data['description'] ?? null,'frequency'=>$data['frequency'],'status'=>'active',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach ($data['heads'] as $head) {
                DB::table('tagore_fee_components')->insert([
                    'fee_structure_id'=>$structureId,'name'=>$head['name'],'code'=>$head['code'],
                    'category'=>$head['category'] ?? null,'amount'=>round((float)$head['amount'],2),
                    'tax_rate'=>0,'is_optional'=>(bool)($head['optional'] ?? false),'status'=>'active',
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
        });
        return back()->with('success','Fee structure created.');
    }

    public function generateDemand(Request $request, FeeService $feeService)
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        abort_unless($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty(), 403);
        $data = $request->validate([
            'student_id'=>['required','integer','exists:users,id'],
            'fee_structure_id'=>['required','integer','exists:tagore_fee_structures,id'],
            'due_date'=>['nullable','date'],
            'concession_amount'=>['nullable','numeric','min:0'],
            'discount_amount'=>['nullable','numeric','min:0'],
        ]);
        $structure = DB::table('tagore_fee_structures')->where('id',$data['fee_structure_id'])->first();
        abort_unless($structure,404);
        $this->authorizeInstitution($userId,(int)$structure->institution_id,$roles);
        $student = DB::table('users')->where('id',$data['student_id'])->first(['id','school_id','usergroup_id']);
        abort_unless($student && (int)$student->usergroup_id === 6,422);
        $institution = DB::table('tagore_institutions')->where('id',$structure->institution_id)->first(['id','school_id']);
        abort_unless($institution && (int)$institution->school_id === (int)$student->school_id,422);
        $components = DB::table('tagore_fee_components')->where('fee_structure_id',$structure->id)->where('status','active')->orderBy('id')->get();
        $items = $components->map(fn($c)=>['fee_component_id'=>$c->id,'fee_head'=>$c->name,'code'=>$c->code,'gross_amount'=>(float)$c->amount,'discount_amount'=>0,'concession_amount'=>0])->all();
        $total = round(array_sum(array_column($items,'gross_amount')),2);
        $discount = round((float)($data['discount_amount'] ?? 0),2);
        $concession = round((float)($data['concession_amount'] ?? 0),2);
        abort_unless($discount + $concession <= $total,422);
        if ($items) { $items[0]['discount_amount']=$discount; $items[0]['concession_amount']=$concession; }
        $id = $feeService->createObligation((int)$student->id,(int)$structure->institution_id,[
            'academic_year_id'=>$structure->academic_year_id,'fee_structure_id'=>$structure->id,'due_date'=>$data['due_date'] ?? null,
            'items'=>$items,'gross_amount'=>$total,'discount_amount'=>$discount,'concession_amount'=>$concession,
        ],$userId);
        return back()->with('success',"Student demand #{$id} created.");
    }

    private function roles(int $userId){return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();}
    private function institutions(int $userId,$roles){$q=DB::table('tagore_institutions as i')->join('schools as s','s.id','=','i.school_id')->where('i.status','active'); if(!$roles->contains('OWNER')){$q->whereIn('i.id',DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')->whereNotNull('institution_id')->pluck('institution_id'));} return $q->orderBy('i.display_name')->get(['i.id','i.display_name','i.school_id']);}
    private function authorizeInstitution(int $userId,int $institutionId,$roles):void{if($roles->contains('OWNER'))return;abort_unless(DB::table('tagore_user_roles')->where('user_id',$userId)->where('institution_id',$institutionId)->where('status','active')->exists(),403);}
}
