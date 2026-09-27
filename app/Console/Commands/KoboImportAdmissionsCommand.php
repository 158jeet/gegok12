<?php

namespace App\Console\Commands;

use App\Services\Kobo\KoboAdmissionImporter;
use Illuminate\Console\Command;

class KoboImportAdmissionsCommand extends Command
{
    protected $signature = 'kobo:import-admissions {assetUid? : Kobo project asset UID}';
    protected $description = 'Import synchronized Kobo submissions into Tagore admissions leads';

    public function handle(): int
    {
        try {
            $stats = app(KoboAdmissionImporter::class)->import($this->argument('assetUid') ?: config('kobo.asset_uid'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->table(['Result', 'Count'], [
            ['Imported', $stats['imported']],
            ['Linked existing', $stats['linked']],
            ['Skipped', $stats['skipped']],
            ['Failed', $stats['failed']],
        ]);

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
