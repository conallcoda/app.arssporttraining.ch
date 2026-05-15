<?php

namespace App\Console\Commands;

use App\Training\ScheduledTrainingSnapshotCompareService;
use Illuminate\Console\Command;

class CompareTrainingSnapshotsCommand extends Command
{
    protected $signature = 'training:snapshot-compare
        {--training-program-id= : Limit the comparison to a single training program}
        {--user-id= : Limit the comparison to a single athlete}
        {--slot-id=* : Limit the comparison to one or more specific slot ids}
        {--from= : Include slots on or after this date (YYYY-MM-DD)}
        {--to= : Include slots on or before this date (YYYY-MM-DD)}
        {--include-locked : Include locked and ambiguous sessions instead of comparing future-open sessions only}
        {--only-mismatches : Only print per-slot lines for mismatches}';

    protected $description = 'Compares stored scheduled snapshots against fresh compilation, focused on future-open cutover parity by default.';

    public function handle(ScheduledTrainingSnapshotCompareService $service): int
    {
        $slotIds = collect((array) $this->option('slot-id'))
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        $result = $service->compare(
            trainingProgramId: $this->filledOption('training-program-id') ? (int) $this->option('training-program-id') : null,
            userId: $this->filledOption('user-id') ? (int) $this->option('user-id') : null,
            fromDate: $this->filledOption('from') ? (string) $this->option('from') : null,
            toDate: $this->filledOption('to') ? (string) $this->option('to') : null,
            slotIds: $slotIds,
            futureOpenOnly: ! (bool) $this->option('include-locked'),
        );

        $onlyMismatches = (bool) $this->option('only-mismatches');

        foreach ($result['results'] as $audit) {
            if ($audit->matches) {
                if (! $onlyMismatches) {
                    $this->line(sprintf('Slot %d matches. [%s]', $audit->slotId, $audit->classification->kind));
                }

                continue;
            }

            $this->warn(sprintf('Slot %d mismatches: %d [%s]', $audit->slotId, $audit->mismatchCount, $audit->classification->kind));

            foreach ($audit->mismatches as $mismatch) {
                $this->line(sprintf(
                    '  - %s [%s] expected=%s actual=%s',
                    $mismatch->path,
                    $mismatch->kind,
                    $this->renderValue($mismatch->expected),
                    $this->renderValue($mismatch->actual),
                ));
            }
        }

        $this->newLine();
        $this->info('Scheduled snapshot compare complete.');
        $this->line('Compared slots: '.$result['compared_slots']);
        $this->line('Matching slots: '.$result['matching_slots']);
        $this->line('Mismatched slots: '.$result['mismatched_slots']);

        return $result['mismatched_slots'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function filledOption(string $name): bool
    {
        $value = $this->option($name);

        return $value !== null && $value !== '';
    }

    private function renderValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
