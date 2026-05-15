<?php

namespace App\Console\Commands;

use App\Training\ScheduledTrainingSnapshotRepairService;
use Illuminate\Console\Command;

class RepairTrainingSnapshotsCommand extends Command
{
    protected $signature = 'training:snapshot-repair
        {--slot-id=* : One or more specific slot ids to repair}
        {--force : Apply the repair to the selected slots}';

    protected $description = 'Explicitly rematerializes selected scheduled sessions, including locked or ambiguous ones.';

    public function handle(ScheduledTrainingSnapshotRepairService $service): int
    {
        $slotIds = collect((array) $this->option('slot-id'))
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        if ($slotIds === []) {
            $this->error('Provide at least one --slot-id for an explicit repair run.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('This command can rewrite locked or ambiguous scheduled snapshots.');
            $this->line('Re-run with --force once you have reviewed the targeted slot ids.');

            return self::FAILURE;
        }

        $result = $service->repair($slotIds);

        $this->info('Scheduled snapshot repair complete.');
        $this->line('Audited slots: '.$result['audited_slots']);
        $this->line('Mismatched slots before repair: '.$result['mismatched_slots']);
        $this->line('Repaired slots: '.$result['repaired_slots']);
        $this->line('Still mismatched after repair: '.$result['still_mismatched_slots']);

        return $result['still_mismatched_slots'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
