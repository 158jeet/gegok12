<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Twilio\Rest\Client as TwilioClient;

class CommunicationService
{
    public function sendCampaign(int $campaignId): array
    {
        $campaign=DB::table('tagore_message_campaigns')->where('id',$campaignId)->lockForUpdate()->first();
        if(!$campaign) throw ValidationException::withMessages(['campaign'=>'Campaign not found.']);
        if(in_array($campaign->status,['sent','sending'],true)) return ['sent'=>(int)$campaign->sent_count,'failed'=>(int)$campaign->failed_count];

        $audience=json_decode((string)$campaign->audience_json,true) ?: [];
        $query=DB::table('users')->whereNull('deleted_at');
        if(!empty($audience['user_ids'])) $query->whereIn('id',array_map('intval',(array)$audience['user_ids']));
        if(!empty($audience['school_ids'])) $query->whereIn('school_id',array_map('intval',(array)$audience['school_ids']));
        if(!empty($audience['usergroup_ids'])) $query->whereIn('usergroup_id',array_map('intval',(array)$audience['usergroup_ids']));
        $users=$query->get(['id','name','email','phone']);

        DB::table('tagore_message_campaigns')->where('id',$campaignId)->update(['status'=>'sending','updated_at'=>now()]);
        $sent=0; $failed=0;
        foreach($users as $user){
            $destination=$this->destination($campaign->channel,$user);
            $delivery=DB::table('tagore_message_deliveries')->where('campaign_id',$campaignId)->where('user_id',$user->id)->where('channel',$campaign->channel)->first();
            if($delivery && $delivery->status==='sent') { $sent++; continue; }
            $deliveryId=$delivery?->id ?: DB::table('tagore_message_deliveries')->insertGetId([
                'campaign_id'=>$campaignId,'user_id'=>$user->id,'channel'=>$campaign->channel,'destination'=>$destination,'status'=>'queued','created_at'=>now(),'updated_at'=>now()
            ]);
            try{
                $this->deliver($campaign->channel,$user,$campaign->title,$campaign->message);
                DB::table('tagore_message_deliveries')->where('id',$deliveryId)->update(['status'=>'sent','sent_at'=>now(),'updated_at'=>now(),'error'=>null]);
                $sent++;
            }catch(\Throwable $e){
                DB::table('tagore_message_deliveries')->where('id',$deliveryId)->update(['status'=>'failed','error'=>substr($e->getMessage(),0,4000),'updated_at'=>now()]);
                $failed++;
            }
        }
        DB::table('tagore_message_campaigns')->where('id',$campaignId)->update(['status'=>$failed?'partial':'sent','sent_count'=>$sent,'failed_count'=>$failed,'updated_at'=>now()]);
        return compact('sent','failed');
    }

    private function destination(string $channel,$user): ?string
    {
        return match($channel){
            'email'=>$user->email,
            'sms','whatsapp'=>$user->phone,
            'push'=>'device-token',
            default=>null,
        };
    }

    private function deliver(string $channel,$user,string $title,string $message): void
    {
        if($channel==='email'){
            if(!$user->email) throw new \RuntimeException('Recipient has no email address.');
            Mail::raw($message,function($mail)use($user,$title){$mail->to($user->email,$user->name)->subject($title);});
            return;
        }

        if($channel==='sms' || $channel==='whatsapp'){
            if(!$user->phone) throw new \RuntimeException('Recipient has no phone number.');
            $sid=config('services.twilio.sid'); $token=config('services.twilio.token');
            if(!$sid || !$token) throw new \RuntimeException('Twilio credentials are not configured.');
            $from=$channel==='whatsapp' ? env('TWILIO_WHATSAPP_FROM') : env('TWILIO_SMS_FROM');
            if(!$from) throw new \RuntimeException('Twilio sender is not configured.');
            $to=$channel==='whatsapp' ? 'whatsapp:'.$user->phone : $user->phone;
            (new TwilioClient($sid,$token))->messages->create($to,['from'=>$from,'body'=>$message]);
            return;
        }

        if($channel==='push'){
            $tokens=DB::table('tagore_user_devices')->where('user_id',$user->id)->pluck('token')->all();
            if(!$tokens) throw new \RuntimeException('Recipient has no registered push device.');
            $messaging=app('firebase.messaging');
            foreach($tokens as $token){
                $messaging->send(\Kreait\Firebase\Messaging\CloudMessage::withTarget('token',$token)->withNotification(['title'=>$title,'body'=>$message]));
            }
            return;
        }

        throw new \RuntimeException('Unsupported communication channel.');
    }
}
