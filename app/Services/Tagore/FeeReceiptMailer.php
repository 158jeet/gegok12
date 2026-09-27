<?php

namespace App\Services\Tagore;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class FeeReceiptMailer
{
    public function send(int $paymentId): bool
    {
        $payment=DB::table('tagore_payments as p')
            ->join('users as s','s.id','=','p.student_id')
            ->join('tagore_institutions as i','i.id','=','p.institution_id')
            ->leftJoin('users as pu','pu.id','=','p.parent_user_id')
            ->where('p.id',$paymentId)->where('p.status','success')
            ->first(['p.*','s.name as student_name','s.name as student','i.display_name as institution','pu.email as parent_email','pu.id as parent_id']);

        if(!$payment) return false;
        $parentId=$payment->parent_id;
        $email=$payment->parent_email;

        if(!$email){
            $parent=DB::table('tagore_parent_students as ps')->join('users as u','u.id','=','ps.parent_user_id')
                ->where('ps.student_id',$payment->student_id)->where('ps.status','active')->whereNotNull('u.email')
                ->orderByDesc('ps.is_primary')->first(['u.id','u.email']);
            $parentId=$parent?->id;
            $email=$parent?->email;
        }

        if(!$email){
            DB::table('tagore_fee_receipt_deliveries')->updateOrInsert(['payment_id'=>$paymentId,'channel'=>'email'],[
                'recipient_user_id'=>$parentId,'recipient_email'=>null,'status'=>'skipped','error'=>'Parent email address is not available.','updated_at'=>now(),'created_at'=>now(),
            ]);
            return false;
        }

        $delivery=DB::table('tagore_fee_receipt_deliveries')->where('payment_id',$paymentId)->where('channel','email')->first();
        if($delivery?->status==='sent') return true;

        $allocations=DB::table('tagore_payment_allocations as a')->leftJoin('tagore_fee_obligations as o','o.id','=','a.fee_obligation_id')
            ->where('a.payment_id',$paymentId)->get(['a.amount','o.due_date']);

        $pdf=Pdf::loadView('tagore.fees.receipt',compact('payment','allocations'))->output();

        try {
            Mail::send('tagore.fees.receipt-email',['payment'=>$payment],function($message) use($email,$payment,$pdf){
                $message->to($email)->subject('Fee Receipt '.$payment->receipt_no.' - '.$payment->institution)
                    ->attachData($pdf,($payment->receipt_no?:'tagore-receipt-'.$payment->id).'.pdf',['mime'=>'application/pdf']);
            });
            DB::table('tagore_fee_receipt_deliveries')->updateOrInsert(['payment_id'=>$paymentId,'channel'=>'email'],[
                'recipient_user_id'=>$parentId,'recipient_email'=>$email,'status'=>'sent','error'=>null,'sent_at'=>now(),'updated_at'=>now(),'created_at'=>now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            DB::table('tagore_fee_receipt_deliveries')->updateOrInsert(['payment_id'=>$paymentId,'channel'=>'email'],[
                'recipient_user_id'=>$parentId,'recipient_email'=>$email,'status'=>'failed','error'=>substr($e->getMessage(),0,2000),'updated_at'=>now(),'created_at'=>now(),
            ]);
            return false;
        }
    }
}
