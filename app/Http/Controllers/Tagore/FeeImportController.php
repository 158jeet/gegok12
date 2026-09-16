<?php
namespace App\Http\Controllers\Tagore;
use App\Http\Controllers\Controller;
use App\Services\Tagore\LegacyFeeMigrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class FeeImportController extends Controller
{
    public function index(Request $request): View
    {
        $userId=(int)$request->user()->id;$roles=$this->roles($userId);
        abort_unless($roles->intersect(['OWNER','PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);
        $institutions=DB::table('tagore_institutions')->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        if(!$roles->contains('OWNER')){$ids=DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')->whereNotNull('institution_id')->pluck('institution_id');$institutions=$institutions->whereIn('id',$ids->all())->values();}
        $years=DB::table('academic_years')->orderByDesc('start_date')->get(['id','name','school_id']);
        $batches=DB::table('tagore_fee_import_batches as b')->join('tagore_institutions as i','i.id','=','b.institution_id')->leftJoin('users as u','u.id','=','b.created_by')->orderByDesc('b.id')->limit(25)->get(['b.*','i.display_name as institution','u.name as creator']);
        return view('tagore.fees.import',compact('institutions','years','batches','roles'));
    }
    public function preview(Request $request,LegacyFeeMigrationService $service){$userId=(int)$request->user()->id;$data=$request->validate(['institution_id'=>['required','integer','exists:tagore_institutions,id'],'academic_year_id'=>['required','integer','exists:academic_years,id'],'file'=>['required','file','mimes:xlsx,xls,csv','max:20480']]);$this->authorizeAccounts($userId,(int)$data['institution_id']);$r=$service->preview($request->file('file')->getRealPath(),(int)$data['institution_id'],(int)$data['academic_year_id'],$userId);return back()->with('success',"Preview {$r['batchId']}: {$r['success']} rows ready, {$r['errors']} rows need review out of {$r['row_count']}.");}
    public function apply(Request $request,int $batchId,LegacyFeeMigrationService $service){$userId=(int)$request->user()->id;$batch=DB::table('tagore_fee_import_batches')->where('id',$batchId)->first();abort_unless($batch,404);$this->authorizeAccounts($userId,(int)$batch->institution_id);$r=$service->apply($batchId,$userId);return back()->with('success',"Import {$batchId} applied: {$r['fee_structures']} structures, {$r['student_fees']} student ledgers, {$r['payments']} payments, {$r['opening_balances']} opening balances, {$r['concessions']} concessions, {$r['transport_routes']} routes and {$r['transport_assignments']} assignments.");}
    private function roles(int $userId){return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();}
    private function authorizeAccounts(int $userId,int $institutionId):void{$roles=$this->roles($userId);abort_unless($roles->intersect(['OWNER','PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);if(!$roles->contains('OWNER'))abort_unless(DB::table('tagore_user_roles')->where('user_id',$userId)->where('institution_id',$institutionId)->where('status','active')->exists(),403);}
}
