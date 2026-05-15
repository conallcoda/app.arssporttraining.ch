<?php

namespace App\Training;

use App\Models\Training\TrainingProgramSlot;
use Carbon\CarbonImmutable;

class ScheduledTrainingSnapshotBackfillService
{
    public function __construct(
        private readonly ScheduledTrainingSnapshotAuditService $auditService,
        private readonly TrainingSessionMaterializer $materializer,
    ) {}

    /**
     * @param  array<int>  $slotIds
     * @return array{
     *     audited_slots: int,
     *     eligible_slots: int,
     *     rebuilt_slots: int,
     *     matching_slots: int,
     *     skipped_locked_past: int,
     *     skipped_ambiguous: int,
     *     skipped_future_filter: int
     * }
     */
    public function backfill(
        ?int $trainingProgramId = null,
        ?int $userId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        array $slotIds = [],
        bool $futureOnly = true,
        bool $force = false,
    ): array {
        $result = [
            'audited_slots' => 0,
            'eligible_slots' => 0,
            'rebuilt_slots' => 0,
            'matching_slots' => 0,
            'skipped_locked_past' => 0,
            'skipped_ambiguous' => 0,
            'skipped_future_filter' => 0,
        ];

        $query = TrainingProgramSlot::query()
            ->with(['trainingProgram.program.exercises', 'exercises.sets.values'])
            ->orderBy('id');

        if ($trainingProgramId !== null) {
            $query->where('training_program_id', $trainingProgramId);
        }

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($slotIds !== []) {
            $query->whereIn('id', $slotIds);
        }

        if ($fromDate !== null) {
            $query->whereDate('datetime', '>=', CarbonImmutable::parse($fromDate)->toDateString());
        }

        if ($toDate !== null) {
            $query->whereDate('datetime', '<=', CarbonImmutable::parse($toDate)->toDateString());
        }

        $query->chunkById(100, function ($slots) use (&$result, $futureOnly, $force): void {
            foreach ($slots as $slot) {
                $audit = $this->auditService->audit($slot);
                $result['audited_slots']++;

                if ($audit->matches) {
                    $result['matching_slots']++;

                    continue;
                }

                if ($audit->classification->isLockedPast()) {
                    $result['skipped_locked_past']++;

                    continue;
                }

                if ($audit->classification->isAmbiguousBoundary()) {
                    $result['skipped_ambiguous']++;

                    continue;
                }

                if ($futureOnly && ! $audit->classification->isFutureOpen()) {
                    $result['skipped_future_filter']++;

                    continue;
                }

                $result['eligible_slots']++;

                if (! $force) {
                    continue;
                }

                $this->materializer->materialize($slot, force: true, ignoreCompiledVersion: true);
                $result['rebuilt_slots']++;
            }
        });

        return $result;
    }
}
