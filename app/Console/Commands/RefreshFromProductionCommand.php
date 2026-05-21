<?php

namespace App\Console\Commands;

use App\Support\Database\RefreshLocalDatabaseFromProductionService;
use Illuminate\Console\Command;
use Throwable;

class RefreshFromProductionCommand extends Command
{
    public const NAME = 'db:refresh-from-production';

    public const SIGNATURE = self::NAME.'
        {dump? : Optional dump filename inside import/dumps or an absolute dump path for the import step}';

    protected $signature = self::SIGNATURE;

    protected $description = 'Pull the latest production dump, run migrate:refresh, and import the production dump in one command';

    public function handle(RefreshLocalDatabaseFromProductionService $service): int
    {
        $dump = $this->argument('dump');

        try {
            $service->run($this, is_string($dump) && $dump !== '' ? $dump : null);
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->info('Local database refresh from production complete.');

        return self::SUCCESS;
    }
}
