<?php

namespace App\Console\Commands;

use App\Services\Kobo\KoboClient;
use App\Services\Kobo\KoboSubmissionSync;
use Illuminate\Console\Command;

class KoboSyncCommand extends Command
{
    protected $signature = 'kobo:sync {assetUid? : Kobo project asset UID}';
    protected $description = 'Synchronize KoboToolbox submissions into the local database';

    public function handle(): int
    {
        $assetUid = $this->argument('assetUid') ?: config('kobo.asset_uid');

        $this->info('Syncing Kobo submissions'.($assetUid ? ' for '.$assetUid : '').'...');

        try {
            $count = app(KoboSubmissionSync::class)->sync($assetUid);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Synchronized {$count} submission(s).");
        return self::SUCCESS;
    }
}