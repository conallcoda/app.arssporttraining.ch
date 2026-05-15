<?php

namespace App\Console\Commands;

use App\Models\Training\TrainingProgramSlot;
use App\Training\ScheduledTrainingSnapshotAuditService;
use Illuminate\Console\Command;

class AuditTrainingSnapshotsCommand extends Command
{
    protected $signature = 'training:snapshot-audit
        {--training-program-id= : Limit the audit to a single training program}
        {--slot-id=* : Limit the audit to one or more specific slot ids}
        {--only-mismatches : Only print per-slot lines for mismatches}';

    protected $description = 'Audits persisted scheduled training snapshots against a fresh compile.';

    public function handle(ScheduledTrainingSnapshotAuditService $service): int
    {
        $slotIds = collect((array) $this->option('slot-id'))
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();
        $trainingProgramId = $this->option('training-program-id');
        $onlyMismatches = (bool) $this->option('only-mismatches');

        $query = TrainingProgramSlot::query()
            ->with(['trainingProgram.program.exercises', 'exercises.sets.values'])
            ->orderBy('id');

        if ($trainingProgramId !== null && $trainingProgramId !== '') {
            $query->where('training_program_id', (int) $trainingProgramId);
        }

        if ($slotIds !== []) {
            $query->whereIn('id', $slotIds);
        }

        $auditedSlots = 0;
        $matchingSlots = 0;
        $mismatchedSlots = 0;
        $classificationCounts = [
            'locked_past' => 0,
            'future_open' => 0,
            'ambiguous_boundary' => 0,
        ];

        $query->chunkById(100, function ($slots) use ($service, $onlyMismatches, &$auditedSlots, &$matchingSlots, &$mismatchedSlots, &$classificationCounts): void {
            foreach ($slots as $slot) {
                $result = $service->audit($slot);
                $auditedSlots++;
                $classificationCounts[$result->classification->kind] = ($classificationCounts[$result->classification->kind] ?? 0) + 1;

                if ($result->matches) {
                    $matchingSlots++;

                    if (! $onlyMismatches) {
                        $this->line(sprintf('Slot %d matches. [%s]', $result->slotId, $result->classification->kind));
                    }

                    continue;
                }

                $mismatchedSlots++;
                $reasonSuffix = $result->classification->reasons === []
                    ? ''
                    : ' reasons='.implode(',', $result->classification->reasons);
                $this->warn(sprintf(
                    'Slot %d mismatches: %d [%s]%s',
                    $result->slotId,
                    $result->mismatchCount,
                    $result->classification->kind,
                    $reasonSuffix,
                ));

                foreach ($result->mismatches as $mismatch) {
                    $this->line(sprintf(
                        '  - %s [%s] expected=%s actual=%s',
                        $mismatch->path,
                        $mismatch->kind,
                        $this->renderValue($mismatch->expected),
                        $this->renderValue($mismatch->actual),
                    ));
                }
            }
        });

        $this->newLine();
        $this->info('Scheduled snapshot audit complete.');
        $this->line('Audited slots: '.$auditedSlots);
        $this->line('Matching slots: '.$matchingSlots);
        $this->line('Mismatched slots: '.$mismatchedSlots);
        $this->line('Locked past slots: '.($classificationCounts['locked_past'] ?? 0));
        $this->line('Future open slots: '.($classificationCounts['future_open'] ?? 0));
        $this->line('Ambiguous boundary slots: '.($classificationCounts['ambiguous_boundary'] ?? 0));

        return $mismatchedSlots > 0 ? self::FAILURE : self::SUCCESS;
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
