<?php

namespace App\Console\Commands;

use App\Services\Tagore\CommunicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendTagoreCampaigns extends Command
{
    protected $signature='tagore:send-campaigns';
    protected $description='Deliver due Tagore communication campaigns.';

    public function handle(CommunicationService $service): int
    {
        $campaigns=DB::table('tagore_message_campaigns')->whereIn('status',['scheduled','queued'])->where(function($q){$q->whereNull('scheduled_at')->orWhere('scheduled_at','<=',now());})->orderBy('id')->limit(50)->get();
        foreach($campaigns as $campaign) {
            $result=$service->sendCampaign((int)$campaign->id);
            $this->line('#'.$campaign->id.' sent='.$result['sent'].' failed='.$result['failed']);
        }
        return self::SUCCESS;
    }
}
