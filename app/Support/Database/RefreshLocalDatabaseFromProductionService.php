<?php

namespace App\Support\Database;

use App\Console\Commands\ImportLatestProductionDumpCommand;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class RefreshLocalDatabaseFromProductionService
{
    public function __construct(
        private readonly Kernel $artisan,
    ) {}

    public function run(Command $command, ?string $dump = null): void
    {
        $command->info('Pulling the latest production dump from scripts/pull-production-db.sh...');
        $this->runPullScript($command);

        $command->newLine();
        $command->info('Refreshing the local database with migrate:refresh...');
        $this->runArtisanCommand($command, 'migrate:refresh', ['--force' => true]);

        $command->newLine();
        $command->info('Importing the production dump from import/dumps...');

        $parameters = [];

        if ($dump !== null) {
            $parameters['dump'] = $dump;
        }

        $this->runArtisanCommand($command, ImportLatestProductionDumpCommand::NAME, $parameters);
    }

    protected function runPullScript(Command $command): void
    {
        $scriptPath = base_path('scripts/pull-production-db.sh');

        if (! File::exists($scriptPath)) {
            throw new RuntimeException("Pull script not found: {$scriptPath}");
        }

        $process = new Process(['bash', $scriptPath], base_path());
        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer) use ($command): void {
            $command->getOutput()->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());

            throw new RuntimeException(
                $errorOutput !== ''
                    ? 'Failed to pull production dump: '.$errorOutput
                    : 'Failed to pull production dump.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function runArtisanCommand(Command $command, string $name, array $parameters = []): void
    {
        $exitCode = $this->artisan->call($name, $parameters, $command->getOutput());

        if ($exitCode !== Command::SUCCESS) {
            throw new RuntimeException("Artisan command [{$name}] failed with exit code {$exitCode}.");
        }
    }
}
