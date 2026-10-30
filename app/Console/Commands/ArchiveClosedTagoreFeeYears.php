<?php

namespace App\Console\Commands;

use App\Services\Tagore\FeeVaultService;
use App\Services\Tagore\FeeVaultArchiveNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveClosedTagoreFeeYears extends Command
{
    protected $signature = 'tagore:archive-closed-fees';
    protected $description = 'Automatically archive completed academic-year fee records into the private owner fee vault';

    public function handle(FeeVaultService $vault, FeeVaultArchiveNotifier $notifier): int
    {
        $rows=DB::table('academic_years as ay')
            ->join('tagore_institutions as i','i.school_id','=','ay.school_id')
            ->whereDate('ay.end_date','<',now()->toDateString())
            ->where('i.status','active')
            ->get(['ay.id as academic_year_id','i.id as institution_id']);

        $deliveryFailures = 0;

        foreach($rows as $row){
            $ownerId=(int)config('tagore.fee_vault_owner_user_id');
            if ($ownerId <= 0) { $this->error('TAGORE_FEE_VAULT_OWNER_USER_ID is not configured.'); return self::FAILURE; }
            $closure = $vault->closeYear((int)$row->academic_year_id,(int)$row->institution_id,$ownerId);
            $closureId = (int) ($closure['id'] ?? 0);
            if ($closureId <= 0 || !$notifier->send($closureId)) {
                $deliveryFailures++;
                $this->error("Encrypted archive email failed for academic year {$row->academic_year_id}, institution {$row->institution_id}. Check tagore_fee_archive_deliveries and application logs.");
                continue;
            }
            $this->line("Archived and emailed encrypted archive for academic year {$row->academic_year_id}, institution {$row->institution_id}");
        }

        $this->info("Checked {$rows->count()} completed academic-year/institution combinations; {$deliveryFailures} archive email deliveries failed.");
        return $deliveryFailures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
