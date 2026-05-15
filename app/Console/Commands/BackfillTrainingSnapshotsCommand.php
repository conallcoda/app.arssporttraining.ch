<?php

namespace App\Console\Commands;

use App\Training\ScheduledTrainingSnapshotBackfillService;
use Illuminate\Console\Command;

class BackfillTrainingSnapshotsCommand extends Command
{
    protected $signature = 'training:snapshot-backfill
        {--training-program-id= : Limit the backfill to a single training program}
        {--user-id= : Limit the backfill to a single athlete}
        {--slot-id=* : Limit the backfill to one or more specific slot ids}
        {--from= : Include slots on or after this date (YYYY-MM-DD)}
        {--to= : Include slots on or before this date (YYYY-MM-DD)}
        {--future-only : Restrict the scan to future-open sessions only}
        {--force : Apply the backfill instead of returning a dry-run report}';

    protected $description = 'Audits scheduled snapshots and rematerializes eligible future-open mismatches.';

    public function handle(ScheduledTrainingSnapshotBackfillService $service): int
    {
        $slotIds = collect((array) $this->option('slot-id'))
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        $force = (bool) $this->option('force');
        $result = $service->backfill(
            trainingProgramId: $this->filledOption('training-program-id') ? (int) $this->option('training-program-id') : null,
            userId: $this->filledOption('user-id') ? (int) $this->option('user-id') : null,
            fromDate: $this->filledOption('from') ? (string) $this->option('from') : null,
            toDate: $this->filledOption('to') ? (string) $this->option('to') : null,
            slotIds: $slotIds,
            futureOnly: (bool) $this->option('future-only'),
            force: $force,
        );

        if (! $force) {
            $this->warn('Dry run only. Re-run with --force to apply the backfill to eligible future-open mismatches.');
        } else {
            $this->info('Scheduled snapshot backfill complete.');
        }

        $this->line('Audited slots: '.$result['audited_slots']);
        $this->line('Eligible slots: '.$result['eligible_slots']);
        $this->line('Rebuilt slots: '.$result['rebuilt_slots']);
        $this->line('Matching slots: '.$result['matching_slots']);
        $this->line('Skipped locked past: '.$result['skipped_locked_past']);
        $this->line('Skipped ambiguous: '.$result['skipped_ambiguous']);
        $this->line('Skipped future filter: '.$result['skipped_future_filter']);

        return self::SUCCESS;
    }

    private function filledOption(string $name): bool
    {
        $value = $this->option($name);

        return $value !== null && $value !== '';
    }
}
