<?php

namespace App\Console\Commands;

use App\Services\Tagore\FeeVaultService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveClosedTagoreFeeYears extends Command
{
    protected $signature = 'tagore:archive-closed-fees';
    protected $description = 'Automatically archive completed academic-year fee records into the private owner fee vault';

    public function handle(FeeVaultService $vault): int
    {
        $rows=DB::table('academic_years as ay')
            ->join('tagore_institutions as i','i.school_id','=','ay.school_id')
            ->whereDate('ay.end_date','<',now()->toDateString())
            ->where('i.status','active')
            ->get(['ay.id as academic_year_id','i.id as institution_id']);

        foreach($rows as $row){
            $vault->closeYear((int)$row->academic_year_id,(int)$row->institution_id,1);
            $this->line("Archived academic year {$row->academic_year_id}, institution {$row->institution_id}");
        }

        $this->info("Checked {$rows->count()} completed academic-year/institution combinations.");
        return self::SUCCESS;
    }
}
