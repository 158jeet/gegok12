<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\FeeService;
use App\Services\Tagore\FeeWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FeeWalletController extends Controller
{
    public function index(Request $request): View
    {
        $roles=$this->roles($request);
        if($roles->contains('PARENT')) $studentIds=DB::table('tagore_parent_students')->where('parent_user_id',$request->user()->id)->where('status','active')->pluck('student_id');
        elseif($roles->contains('STUDENT')) $studentIds=collect([$request->user()->id]);
        else abort_unless($roles->intersect(['OWNER','PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403)->with();
        $wallets=DB::table('tagore_fee_wallets')->whereIn('student_id',$studentIds)->get();
        return view('tagore.fees.wallet',compact('wallets','roles'));
    }

    public function credit(Request $request,FeeWalletService $wallets)
    {
        $roles=$this->roles($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);
        $d=$request->validate(['institution_id'=>'required|integer','student_id'=>'required|integer','amount'=>'required|numeric|min:0.01','notes'=>'nullable|string|max:1000']);
        abort_unless(DB::table('tagore_institutions')->where('id',$d['institution_id'])->where('status','active')->exists(),403);
        $wallets->credit((int)$d['student_id'],(int)$d['institution_id'],(float)$d['amount'],(int)$request->user()->id,$d['notes']??null);
        return back()->with('success','Wallet credited.');
    }

    public function pay(Request $request,FeeWalletService $wallets,FeeService $fees)
    {
        $roles=$this->roles($request);
        $d=$request->validate(['student_id'=>'required|integer','institution_id'=>'required|integer','amount'=>'required|numeric|min:0.01']);
        if($roles->contains('PARENT')) abort_unless(DB::table('tagore_parent_students')->where('parent_user_id',$request->user()->id)->where('student_id',$d['student_id'])->where('status','active')->exists(),403);
        elseif($roles->contains('STUDENT')) abort_unless((int)$request->user()->id===(int)$d['student_id'],403);
        else abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);
        $paymentId=$wallets->payFee((int)$d['student_id'],(int)$d['institution_id'],(float)$d['amount'],(int)$request->user()->id,$fees);
        return back()->with('success','Wallet payment recorded (#'.$paymentId.').');
    }

    private function roles(Request $request)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$request->user()->id)->where('ur.status','active')->pluck('r.code')->unique()->values();
    }
}
