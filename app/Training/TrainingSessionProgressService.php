<?php

namespace App\Training;

use App\Models\Training\TrainingProgramSlotExercise;
use Illuminate\Support\Facades\DB;

class TrainingSessionProgressService
{
    public function __construct(
        private readonly TrainingSessionStatusService $statusService,
    ) {}

    public function markExerciseCompleted(TrainingProgramSlotExercise $exercise): void
    {
        DB::transaction(function () use ($exercise): void {
            $exercise->loadMissing('slot', 'sets');

            $now = now();

            $exercise->sets()->update([
                'completed_at' => $now,
                'skipped_at' => null,
            ]);

            $this->statusService->recalculateExercise($exercise->fresh('slot', 'sets.values'));
        });
    }

    public function markExerciseSkipped(TrainingProgramSlotExercise $exercise): void
    {
        DB::transaction(function () use ($exercise): void {
            $exercise->loadMissing('slot', 'sets');

            $now = now();

            $exercise->sets()->update([
                'completed_at' => null,
                'skipped_at' => $now,
            ]);

            $this->statusService->recalculateExercise($exercise->fresh('slot', 'sets.values'));
        });
    }
}
