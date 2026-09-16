<?php
namespace App\Http\Controllers\Tagore;
use App\Http\Controllers\Controller;
use App\Services\Tagore\LegacyFeeImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class FeeImportController extends Controller
{
    public function index(Request $request): View
    {
        $userId=(int)$request->user()->id; $roles=$this->roles($userId);
        abort_unless($roles->intersect(['OWNER','PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);
        $institutions=DB::table('tagore_institutions')->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        if(!$roles->contains('OWNER')){$ids=DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')->whereNotNull('institution_id')->pluck('institution_id');$institutions=$institutions->whereIn('id',$ids->all())->values();}
        $batches=DB::table('tagore_fee_import_batches as b')->join('tagore_institutions as i','i.id','=','b.institution_id')->leftJoin('users as u','u.id','=','b.created_by')->orderByDesc('b.id')->limit(20)->get(['b.*','i.display_name as institution','u.name as creator']);
        return view('tagore.fees.import',compact('institutions','batches','roles'));
    }
    public function preview(Request $request, LegacyFeeImportService $service)
    {
        $userId=(int)$request->user()->id;
        $data=$request->validate(['institution_id'=>['required','integer','exists:tagore_institutions,id'],'academic_year_id'=>['nullable','integer','exists:academic_years,id'],'file'=>['required','file','mimes:xlsx,xls,csv','max:20480']]);
        $this->authorizeAccounts($userId,(int)$data['institution_id']);
        $result=$service->preview($request->file('file')->getRealPath(),(int)$data['institution_id'],$data['academic_year_id']??null,$userId);
        return back()->with('success',"Preview created: {$result['success']} rows ready, {$result['errors']} rows need review.");
    }
    public function apply(Request $request,int $batchId,LegacyFeeImportService $service)
    {
        $userId=(int)$request->user()->id; $batch=DB::table('tagore_fee_import_batches')->where('id',$batchId)->first(); abort_unless($batch,404);
        $this->authorizeAccounts($userId,(int)$batch->institution_id); $count=$service->applyOpeningBalances($batchId,$userId);
        return back()->with('success',"Applied {$count} opening balances. Unmatched rows were not imported.");
    }
    private function roles(int $userId){return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();}
    private function authorizeAccounts(int $userId,int $institutionId):void{$roles=$this->roles($userId);abort_unless($roles->intersect(['OWNER','PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);if(!$roles->contains('OWNER'))abort_unless(DB::table('tagore_user_roles')->where('user_id',$userId)->where('institution_id',$institutionId)->where('status','active')->exists(),403);}
}
