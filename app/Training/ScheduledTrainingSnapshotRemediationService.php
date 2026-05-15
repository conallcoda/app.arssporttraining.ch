<?php

namespace App\Training;

class ScheduledTrainingSnapshotRemediationService
{
    public function __construct(
        private readonly ScheduledTrainingSnapshotBackfillService $backfillService,
        private readonly ScheduledTrainingSnapshotCompareService $compareService,
    ) {}

    /**
     * @param  array<int>  $slotIds
     * @return array{
     *     backfill: array{
     *         audited_slots: int,
     *         eligible_slots: int,
     *         rebuilt_slots: int,
     *         matching_slots: int,
     *         skipped_locked_past: int,
     *         skipped_ambiguous: int,
     *         skipped_future_filter: int
     *     },
     *     compare: array{
     *         compared_slots: int,
     *         matching_slots: int,
     *         mismatched_slots: int,
     *         results: array<int, \App\Data\Training\Audit\ScheduledSnapshotAuditResultData>
     *     }
     * }
     */
    public function remediate(
        ?int $trainingProgramId = null,
        ?int $userId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        array $slotIds = [],
    ): array {
        $backfill = $this->backfillService->backfill(
            trainingProgramId: $trainingProgramId,
            userId: $userId,
            fromDate: $fromDate,
            toDate: $toDate,
            slotIds: $slotIds,
            futureOnly: true,
            force: true,
        );

        $compare = $this->compareService->compare(
            trainingProgramId: $trainingProgramId,
            userId: $userId,
            fromDate: $fromDate,
            toDate: $toDate,
            slotIds: $slotIds,
            futureOpenOnly: true,
        );

        return [
            'backfill' => $backfill,
            'compare' => $compare,
        ];
    }
}
