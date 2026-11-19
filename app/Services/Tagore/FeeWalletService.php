<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeWalletService
{
    public function credit(int $studentId,int $institutionId,float $amount,?int $createdBy=null,?string $notes=null): void
    {
        if($amount<=0) throw ValidationException::withMessages(['amount'=>'Wallet credit must be greater than zero.']);
        DB::transaction(function()use($studentId,$institutionId,$amount,$createdBy,$notes){
            $wallet=DB::table('tagore_fee_wallets')->where('student_id',$studentId)->where('institution_id',$institutionId)->lockForUpdate()->first();
            $walletId=$wallet?->id;
            if(!$walletId) $walletId=DB::table('tagore_fee_wallets')->insertGetId(['institution_id'=>$institutionId,'student_id'=>$studentId,'balance'=>0,'created_at'=>now(),'updated_at'=>now()]);
            $wallet=DB::table('tagore_fee_wallets')->where('id',$walletId)->lockForUpdate()->first();
            $balance=round((float)$wallet->balance+$amount,2);
            DB::table('tagore_fee_wallets')->where('id',$walletId)->update(['balance'=>$balance,'updated_at'=>now()]);
            DB::table('tagore_fee_wallet_transactions')->insert(['wallet_id'=>$walletId,'type'=>'credit','amount'=>$amount,'balance_after'=>$balance,'created_by'=>$createdBy,'notes'=>$notes,'created_at'=>now(),'updated_at'=>now()]);
        });
    }

    public function payFee(int $studentId,int $institutionId,float $amount,int $createdBy,FeeService $fees): int
    {
        if($amount<=0) throw ValidationException::withMessages(['amount'=>'Wallet payment must be greater than zero.']);
        return DB::transaction(function()use($studentId,$institutionId,$amount,$createdBy,$fees){
            $wallet=DB::table('tagore_fee_wallets')->where('student_id',$studentId)->where('institution_id',$institutionId)->lockForUpdate()->first();
            if(!$wallet || (float)$wallet->balance+0.009<$amount) throw ValidationException::withMessages(['wallet'=>'Insufficient wallet balance.']);
            $paymentId=$fees->recordOfflinePayment($studentId,$institutionId,$amount,null,$createdBy,'Wallet payment',[],'wallet');
            $balance=round((float)$wallet->balance-$amount,2);
            DB::table('tagore_fee_wallets')->where('id',$wallet->id)->update(['balance'=>$balance,'updated_at'=>now()]);
            DB::table('tagore_fee_wallet_transactions')->insert(['wallet_id'=>$wallet->id,'type'=>'debit','amount'=>$amount,'balance_after'=>$balance,'reference_type'=>'tagore_payment','reference_id'=>$paymentId,'created_by'=>$createdBy,'notes'=>'Fee payment from wallet','created_at'=>now(),'updated_at'=>now()]);
            return $paymentId;
        });
    }
}
