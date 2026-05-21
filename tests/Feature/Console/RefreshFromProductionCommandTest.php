<?php

use App\Console\Commands\RefreshFromProductionCommand;
use App\Support\Database\RefreshLocalDatabaseFromProductionService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('runs the refresh-from-production workflow through the service', function () {
    $service = new class extends RefreshLocalDatabaseFromProductionService
    {
        public ?string $dump = null;

        public bool $ran = false;

        public function __construct() {}

        public function run(Command $command, ?string $dump = null): void
        {
            $this->ran = true;
            $this->dump = $dump;

            $command->line('Workflow stub executed.');
        }
    };

    $this->app->instance(RefreshLocalDatabaseFromProductionService::class, $service);

    $this->artisan(RefreshFromProductionCommand::NAME, ['dump' => 'latest.sql.gz'])
        ->expectsOutputToContain('Workflow stub executed.')
        ->expectsOutputToContain('Local database refresh from production complete.')
        ->assertExitCode(0);

    expect($service->ran)->toBeTrue()
        ->and($service->dump)->toBe('latest.sql.gz');
});

it('returns a failure exit code when the workflow service throws', function () {
    $service = new class extends RefreshLocalDatabaseFromProductionService
    {
        public function __construct() {}

        public function run(Command $command, ?string $dump = null): void
        {
            throw new RuntimeException('Refresh workflow failed.');
        }
    };

    $this->app->instance(RefreshLocalDatabaseFromProductionService::class, $service);

    $this->artisan(RefreshFromProductionCommand::NAME)
        ->expectsOutputToContain('Refresh workflow failed.')
        ->assertExitCode(1);
});
