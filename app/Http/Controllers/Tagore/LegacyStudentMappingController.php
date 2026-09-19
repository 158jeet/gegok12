<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Services\Tagore\LegacyStudentMatcher;

class LegacyStudentMappingController extends Controller
{
    public function index(Request $request, int $batchId): View
    {
        $userId = (int)$request->user()->id;
        $batch = DB::table('tagore_fee_import_batches')->where('id', $batchId)->first();
        abort_unless($batch, 404);
        $this->authorize($userId, (int)$batch->institution_id);

        $rows = DB::table('tagore_fee_import_rows as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.student_id')
            ->where('r.batch_id', $batchId)
            ->where('r.status', 'error')
            ->orderBy('r.row_number')
            ->get(['r.*', 'u.name as matched_name']);
        $schoolId = DB::table('tagore_institutions')->where('id', $batch->institution_id)->value('school_id');
        $students = DB::table('users')->where('school_id', $schoolId)->where('usergroup_id', 6)->orderBy('name')->get(['id','name']);
        return view('tagore.fees.mapping', compact('batch','rows','students'));
    }

    public function suggestions(Request $request, int $batchId): View
    {
        $batch = DB::table('tagore_fee_import_batches')->where('id', $batchId)->first();
        abort_unless($batch, 404);
        $this->authorize((int) $request->user()->id, (int) $batch->institution_id);
        $report = app(LegacyStudentMatcher::class)->preview($batchId);
        return view('tagore.fees.mapping-suggestions', compact('batch', 'report'));
    }

    public function autoMatch(Request $request, int $batchId)
    {
        $batch = DB::table('tagore_fee_import_batches')->where('id', $batchId)->first();
        abort_unless($batch, 404);
        $this->authorize((int) $request->user()->id, (int) $batch->institution_id);
        $result = app(LegacyStudentMatcher::class)->applyHighConfidence($batchId, (int) $request->user()->id);
        return back()->with('success', 'High-confidence legacy student matches applied: '.$result['mapped'].'.');
    }

    public function store(Request $request, int $batchId, int $rowId)
    {
        $userId = (int)$request->user()->id;
        $batch = DB::table('tagore_fee_import_batches')->where('id', $batchId)->first();
        abort_unless($batch, 404);
        $this->authorize($userId, (int)$batch->institution_id);
        $data = $request->validate(['student_id'=>['required','integer','exists:users,id']]);
        $row = DB::table('tagore_fee_import_rows')->where('id',$rowId)->where('batch_id',$batchId)->first();
        abort_unless($row,404);
        $schoolId = DB::table('tagore_institutions')->where('id',$batch->institution_id)->value('school_id');
        abort_unless(DB::table('users')->where('id',$data['student_id'])->where('school_id',$schoolId)->where('usergroup_id',6)->exists(),422);
        $key = trim((string)$row->external_student_key);
        abort_unless($key !== '',422);
        DB::transaction(function() use ($batch,$row,$data,$userId,$key) {
            DB::table('tagore_legacy_student_mappings')->updateOrInsert(
                ['institution_id'=>$batch->institution_id,'source_system'=>'legacy_erp','source_key'=>$key],
                ['student_id'=>$data['student_id'],'created_by'=>$userId,'updated_at'=>now(),'created_at'=>now()]
            );
            DB::table('tagore_fee_import_rows')->where('id',$row->id)->update(['student_id'=>$data['student_id'],'status'=>'ready','error_message'=>null,'updated_at'=>now()]);
        });
        $errors = DB::table('tagore_fee_import_rows')->where('batch_id',$batchId)->where('status','error')->count();
        DB::table('tagore_fee_import_batches')->where('id',$batchId)->update(['error_count'=>$errors,'success_count'=>DB::table('tagore_fee_import_rows')->where('batch_id',$batchId)->where('status','ready')->count(),'status'=>$errors?'needs_review':'ready','updated_at'=>now()]);
        return back()->with('success','Legacy student mapping saved.');
    }

    private function authorize(int $userId, int $institutionId): void
    {
        $roles = DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code');
        abort_unless($roles->intersect(['OWNER','PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);
        if (!$roles->contains('OWNER')) abort_unless(DB::table('tagore_user_roles')->where('user_id',$userId)->where('institution_id',$institutionId)->where('status','active')->exists(),403);
    }
}
