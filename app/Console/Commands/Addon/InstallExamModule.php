<?php

namespace App\Console\Commands\Addon;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallExamModule extends Command
{
    protected $signature = 'module:install-exam';

    protected $description = 'Installs the native Tagore examination module without a private Composer package';

    public function handle(): int
    {
        $migration = 'database/migrations/2026_10_30_090000_create_tagore_examination_engine.php';
        $this->info('Installing the native Tagore examination module.');
        $exitCode = Artisan::call('migrate', ['--path' => $migration, '--force' => true]);
        $this->output->write(Artisan::output());

        if ($exitCode !== 0) {
            $this->error('Examination migration did not complete. Review the migration output before retrying.');
            return self::FAILURE;
        }

        $this->info('Native examination module migration completed. No private Git repository or Composer token is required.');
        return self::SUCCESS;
    }
}
