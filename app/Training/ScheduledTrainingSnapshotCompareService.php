<?php

namespace App\Training;

use App\Data\Training\Audit\ScheduledSnapshotAuditResultData;
use App\Models\Training\TrainingProgramSlot;
use Carbon\CarbonImmutable;

class ScheduledTrainingSnapshotCompareService
{
    public function __construct(
        private readonly ScheduledTrainingSnapshotAuditService $auditService,
    ) {}

    /**
     * @param  array<int>  $slotIds
     * @return array{
     *     compared_slots: int,
     *     matching_slots: int,
     *     mismatched_slots: int,
     *     results: array<int, ScheduledSnapshotAuditResultData>
     * }
     */
    public function compare(
        ?int $trainingProgramId = null,
        ?int $userId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        array $slotIds = [],
        bool $futureOpenOnly = true,
    ): array {
        $results = [];

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

        $query->chunkById(100, function ($slots) use (&$results, $futureOpenOnly): void {
            foreach ($slots as $slot) {
                $audit = $this->auditService->audit($slot);

                if ($futureOpenOnly && ! $audit->classification->isFutureOpen()) {
                    continue;
                }

                $results[] = $audit;
            }
        });

        $matching = collect($results)->where('matches', true)->count();

        return [
            'compared_slots' => count($results),
            'matching_slots' => $matching,
            'mismatched_slots' => count($results) - $matching,
            'results' => $results,
        ];
    }
}
