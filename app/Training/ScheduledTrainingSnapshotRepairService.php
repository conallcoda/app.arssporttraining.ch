<?php

namespace App\Training;

use App\Models\Training\TrainingProgramSlot;

class ScheduledTrainingSnapshotRepairService
{
    public function __construct(
        private readonly ScheduledTrainingSnapshotAuditService $auditService,
        private readonly TrainingSessionMaterializer $materializer,
    ) {}

    /**
     * @param  array<int>  $slotIds
     * @return array{
     *     audited_slots: int,
     *     repaired_slots: int,
     *     mismatched_slots: int,
     *     still_mismatched_slots: int
     * }
     */
    public function repair(array $slotIds): array
    {
        $result = [
            'audited_slots' => 0,
            'repaired_slots' => 0,
            'mismatched_slots' => 0,
            'still_mismatched_slots' => 0,
        ];

        TrainingProgramSlot::query()
            ->with(['trainingProgram.program.exercises', 'exercises.sets.values'])
            ->whereIn('id', $slotIds)
            ->orderBy('id')
            ->chunkById(100, function ($slots) use (&$result): void {
                foreach ($slots as $slot) {
                    $before = $this->auditService->audit($slot);
                    $result['audited_slots']++;

                    if (! $before->matches) {
                        $result['mismatched_slots']++;
                    }

                    $this->materializer->materialize(
                        $slot,
                        force: true,
                        ignoreCompiledVersion: true,
                        allowImmutableRewrite: true,
                    );
                    $result['repaired_slots']++;

                    $after = $this->auditService->audit($slot->fresh());

                    if (! $after->matches) {
                        $result['still_mismatched_slots']++;
                    }
                }
            });

        return $result;
    }
}
