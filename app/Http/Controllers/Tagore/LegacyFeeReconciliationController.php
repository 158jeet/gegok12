<?php
namespace App\Http\Controllers\Tagore;
use App\Http\Controllers\Controller;
use App\Services\Tagore\LegacyFeeReconciliationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class LegacyFeeReconciliationController extends Controller
{
    public function show(Request $request,int $batchId,LegacyFeeReconciliationService $service):View
    {
        $userId=(int)$request->user()->id;
        $batch=DB::table('tagore_fee_import_batches')->where('id',$batchId)->first();
        abort_unless($batch,404);
        $this->authorizeInstitution($userId,(int)$batch->institution_id);
        $report=$service->report($batchId);
        return view('tagore.fees.reconciliation',compact('batch','report'));
    }
    private function authorizeInstitution(int $userId,int $institutionId):void
    {
        $roles=DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code');
        abort_unless($roles->intersect(['OWNER','PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);
        if(!$roles->contains('OWNER')) abort_unless(DB::table('tagore_user_roles')->where('user_id',$userId)->where('institution_id',$institutionId)->where('status','active')->exists(),403);
    }
}