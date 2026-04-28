<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class LiveImportDatabaseCommand extends Command
{
    protected $signature = 'db:live-import';

    protected $description = 'Export the remote database via SSH and download the export locally';

    private string $remotePath = '/home/sufocani/www/test.app.arssporttraining.ch';

    public function handle(): int
    {
        $this->info('Running db:export on remote server...');

        $export = $this->runProcess([
            'ssh', 'coda',
            "cd {$this->remotePath} && /usr/local/php84/bin/php artisan db:export",
        ]);

        if (! $export->isSuccessful()) {
            $this->error('Remote db:export failed:');
            $this->line($export->getErrorOutput());

            return 1;
        }

        $this->line($export->getOutput());

        $this->info('Finding most recent export folder...');

        $ls = $this->runProcess([
            'ssh', 'coda',
            "ls -1t {$this->remotePath}/import/database/ | head -1",
        ]);

        if (! $ls->isSuccessful()) {
            $this->error('Failed to list remote export folders.');

            return 1;
        }

        $timestamp = trim($ls->getOutput());

        if (empty($timestamp)) {
            $this->error('No export folder found on remote.');

            return 1;
        }

        $this->info("Latest export: {$timestamp}");

        $localPath = base_path("import/database/{$timestamp}");
        File::ensureDirectoryExists($localPath);

        $this->info('Downloading export...');

        $scp = $this->runProcess([
            'scp', '-r',
            "coda:{$this->remotePath}/import/database/{$timestamp}/.",
            $localPath,
        ], 120);

        if (! $scp->isSuccessful()) {
            $this->error('SCP download failed:');
            $this->line($scp->getErrorOutput());

            return 1;
        }

        $this->newLine();
        $this->info("Import complete: import/database/{$timestamp}");

        return 0;
    }

    private function runProcess(array $command, int $timeout = 60): Process
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->run();

        return $process;
    }
}
