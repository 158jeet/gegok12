<?php

namespace App\Console\Commands;

use App\Services\Tagore\CommunicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunTagoreAutomations extends Command
{
    protected $signature='tagore:run-automations';
    protected $description='Execute enabled Tagore automation rules on their configured schedule.';

    public function handle(CommunicationService $communication): int
    {
        $rules=DB::table('tagore_automation_rules')->where('enabled',true)->where(function($q){
            $q->whereNull('schedule')->orWhereIn('schedule',['every_minute','minute']);
        })->orderBy('id')->limit(100)->get();

        foreach($rules as $rule){
            $runId=DB::table('tagore_automation_runs')->insertGetId([
                'rule_id'=>$rule->id,'status'=>'running','started_at'=>now(),'created_at'=>now(),'updated_at'=>now()
            ]);
            try{
                $config=json_decode((string)$rule->config_json,true) ?: [];
                $output='No action executed.';
                if($rule->action==='send.notification'){
                    $campaignId=DB::table('tagore_message_campaigns')->insertGetId([
                        'institution_id'=>$rule->institution_id,'title'=>$config['title']??$rule->name,
                        'channel'=>$config['channel']??'email','audience_json'=>json_encode($config['audience']??[]),
                        'message'=>$config['message']??$rule->name,'status'=>'queued','created_by'=>null,
                        'created_at'=>now(),'updated_at'=>now()
                    ]);
                    $result=$communication->sendCampaign($campaignId);
                    $output='Campaign #'.$campaignId.' sent='.$result['sent'].' failed='.$result['failed'];
                } elseif($rule->action==='log'){
                    $output='Automation executed at '.now()->toDateTimeString();
                } else {
                    throw new \RuntimeException('Unsupported automation action: '.$rule->action);
                }

                DB::table('tagore_automation_runs')->where('id',$runId)->update(['status'=>'success','output'=>$output,'finished_at'=>now(),'updated_at'=>now()]);
                DB::table('tagore_automation_rules')->where('id',$rule->id)->update(['last_run_at'=>now(),'updated_at'=>now()]);
                $this->line('#'.$rule->id.' '.$output);
            }catch(\Throwable $e){
                DB::table('tagore_automation_runs')->where('id',$runId)->update(['status'=>'failed','error'=>substr($e->getMessage(),0,4000),'finished_at'=>now(),'updated_at'=>now()]);
                $this->error('#'.$rule->id.' '.$e->getMessage());
            }
        }
        return self::SUCCESS;
    }
}
