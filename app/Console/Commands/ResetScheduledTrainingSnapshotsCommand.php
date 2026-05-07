<?php

namespace App\Console\Commands;

use App\Training\ScheduledTrainingSnapshotResetService;
use Illuminate\Console\Command;

class ResetScheduledTrainingSnapshotsCommand extends Command
{
    protected $signature = 'training:reset-scheduled-snapshots
        {--training-program-id= : Limit the reset to a single training program}
        {--scope=all : Which plan grid overrides to clear: all or scheduled}
        {--force : Apply the reset instead of returning a safety warning}';

    protected $description = 'Clears plan grid overrides, removes scheduled actual values, and regenerates planned slot values from canonical settings.';

    public function handle(ScheduledTrainingSnapshotResetService $service): int
    {
        if (! $this->option('force')) {
            $this->warn('This command mutates scheduled training snapshots and clears persisted grid overrides.');
            $this->line('Re-run with --force after reviewing the current backup/commit point.');

            return self::FAILURE;
        }

        $trainingProgramId = $this->option('training-program-id');
        $scope = (string) $this->option('scope');
        $clearAllPlanGridOverrides = $scope !== 'scheduled';

        $result = $service->reset(
            trainingProgramId: $trainingProgramId !== null && $trainingProgramId !== '' ? (int) $trainingProgramId : null,
            clearAllPlanGridOverrides: $clearAllPlanGridOverrides,
        );

        $this->info('Scheduled training snapshots reset.');
        $this->line('Cleared override rows: '.$result['cleared_override_rows']);
        $this->line('Reset slots: '.$result['reset_slots']);

        return self::SUCCESS;
    }
}
