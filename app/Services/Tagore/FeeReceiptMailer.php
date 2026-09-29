<?php

namespace App\Services\Tagore;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

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
        $institutionSettings = json_decode((string) DB::table('tagore_institutions')->where('id',$payment->institution_id)->value('settings_json'), true) ?: [];
        $mailConfig = $this->mailConfig((int)$payment->institution_id, $institutionSettings);

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
            $mailer = $mailConfig ? Mail::build($mailConfig) : Mail::mailer(config('mail.default'));
            $mailer->send('tagore.fees.receipt-email',['payment'=>$payment],function($message) use($email,$payment,$pdf,$mailConfig){
                if (!empty($mailConfig['from_address'])) $message->from($mailConfig['from_address'], $mailConfig['from_name'] ?? config('mail.from.name'));
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

    private function mailConfig(int $institutionId, array $settings): ?array
    {
        $profiles = [];
        $raw = trim((string) config('tagore.mail_profiles_json', ''));
        if ($raw !== '') {
            try { $profiles = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) { Log::warning('Invalid TAGORE_MAIL_PROFILES_JSON', ['error'=>$e->getMessage()]); }
        }
        $profile = is_array($profiles) ? ($profiles[(string)$institutionId] ?? $profiles[$institutionId] ?? null) : null;
        $profile = is_array($profile) ? $profile : [];
        $from = is_array($settings['mail'] ?? null) ? $settings['mail'] : [];

        if (!empty($profile['username'])) {
            return [
                'transport'=>'smtp',
                'host'=>$profile['host'] ?? 'smtp.gmail.com',
                'port'=>(int)($profile['port'] ?? 587),
                'encryption'=>$profile['encryption'] ?? 'tls',
                'username'=>$profile['username'],
                'password'=>$profile['password'] ?? '',
                'timeout'=>(int)($profile['timeout'] ?? 30),
                'from_address'=>$profile['from_address'] ?? $from['from_address'] ?? $profile['username'],
                'from_name'=>$profile['from_name'] ?? $from['from_name'] ?? $payment->institution ?? config('mail.from.name'),
            ];
        }

        return !empty($from['from_address']) ? [
            'transport'=>'smtp',
            'host'=>config('mail.host'),
            'port'=>(int)config('mail.port'),
            'encryption'=>config('mail.encryption'),
            'username'=>config('mail.username'),
            'password'=>config('mail.password'),
            'timeout'=>(int)config('mail.timeout',30),
            'from_address'=>$from['from_address'],
            'from_name'=>$from['from_name'] ?? config('mail.from.name'),
        ] : null;
    }
}
