<?php

namespace App\Console\Commands;

use App\Training\ScheduledTrainingSnapshotRemediationService;
use Illuminate\Console\Command;

class FixTrainingSnapshotDriftCommand extends Command
{
    public const SIGNATURE = 'data:fix-training-snapshot-drift';

    protected $signature = self::SIGNATURE.'
        {--training-program-id= : Limit the remediation to a single training program}
        {--user-id= : Limit the remediation to a single athlete}
        {--slot-id=* : Limit the remediation to one or more specific slot ids}
        {--from= : Include slots on or after this date (YYYY-MM-DD)}
        {--to= : Include slots on or before this date (YYYY-MM-DD)}';

    protected $description = 'Repairs future-open scheduled snapshot drift and verifies the result against fresh compiler output.';

    public function handle(ScheduledTrainingSnapshotRemediationService $service): int
    {
        $slotIds = collect((array) $this->option('slot-id'))
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        $result = $service->remediate(
            trainingProgramId: $this->filledOption('training-program-id') ? (int) $this->option('training-program-id') : null,
            userId: $this->filledOption('user-id') ? (int) $this->option('user-id') : null,
            fromDate: $this->filledOption('from') ? (string) $this->option('from') : null,
            toDate: $this->filledOption('to') ? (string) $this->option('to') : null,
            slotIds: $slotIds,
        );

        $backfill = $result['backfill'];
        $compare = $result['compare'];

        $this->info('Training snapshot drift remediation complete.');
        $this->line('Backfill audited slots: '.$backfill['audited_slots']);
        $this->line('Backfill eligible slots: '.$backfill['eligible_slots']);
        $this->line('Backfill rebuilt slots: '.$backfill['rebuilt_slots']);
        $this->line('Backfill skipped locked past: '.$backfill['skipped_locked_past']);
        $this->line('Backfill skipped ambiguous: '.$backfill['skipped_ambiguous']);
        $this->line('Compare checked future-open slots: '.$compare['compared_slots']);
        $this->line('Compare matching future-open slots: '.$compare['matching_slots']);
        $this->line('Compare mismatched future-open slots: '.$compare['mismatched_slots']);

        if ($compare['mismatched_slots'] > 0) {
            $this->warn('Future-open snapshot mismatches remain after remediation.');

            foreach ($compare['results'] as $audit) {
                if ($audit->matches) {
                    continue;
                }

                $this->line(sprintf('Slot %d mismatches: %d [%s]', $audit->slotId, $audit->mismatchCount, $audit->classification->kind));
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function filledOption(string $name): bool
    {
        $value = $this->option($name);

        return $value !== null && $value !== '';
    }
}
