<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\FeeEditorService;
use App\Services\Tagore\FeeVaultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeEditorController extends Controller
{
    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')
            ->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();
    }

    private function assertEditor($roles): void
    {
        abort_unless($roles->contains('OWNER') || $roles->contains('FEE_EDITOR'),403);
    }

    public function index(Request $request)
    {
        $roles=$this->roles((int)$request->user()->id); $this->assertEditor($roles);
        $institutions=DB::table('tagore_institutions')->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        return view('tagore.fees.editor',compact('institutions'));
    }

    public function bulkAdjust(Request $request, FeeEditorService $service)
    {
        $roles=$this->roles((int)$request->user()->id); $this->assertEditor($roles);
        $data=$request->validate([
            'institution_id'=>['required','integer'],
            'academic_year_id'=>['nullable','integer'],
            'fee_structure_id'=>['nullable','integer'],
            'student_id'=>['nullable','integer'],
            'min_percent'=>['required','numeric','between:-100,100'],
            'max_percent'=>['required','numeric','between:-100,100'],
        ]);
        $count=$service->bulkAdjust($data,$data['min_percent'],$data['max_percent'],(int)$request->user()->id);
        return back()->with('success',\"{$count} fee records adjusted. Opening balances were excluded. Each applied percentage is stored in the audit trail.\");
    }

    public function closeYear(Request $request, FeeVaultService $vault)
    {
        $roles=$this->roles((int)$request->user()->id);
        abort_unless($roles->contains('OWNER'),403);
        $data=$request->validate(['institution_id'=>['required','integer'],'academic_year_id'=>['required','integer']]);
        $result=$vault->closeYear((int)$data['academic_year_id'],(int)$data['institution_id'],(int)$request->user()->id);
        return back()->with('success','Fee year archived. The original vault copy is immutable and remains separate from ERP editing.');
    }

    public function vaultStudent(Request $request, int $studentId, FeeVaultService $vault)
    {
        $roles=$this->roles((int)$request->user()->id);
        abort_unless($roles->contains('OWNER'),403);
        $student=DB::table('users')->where('id',$studentId)->first(['id','name','email']);
        abort_unless($student,404);
        DB::table('tagore_fee_vault_access_events')->insert(['owner_user_id'=>$request->user()->id,'student_id'=>$studentId,'action'=>'VIEW_STUDENT_ARCHIVE','created_at'=>now(),'updated_at'=>now()]);
        $archives=$vault->studentArchives($studentId);
        return view('tagore.fees.vault-student',compact('student','archives'));
    }
}
