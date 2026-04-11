<?php

namespace App\Training;

use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use Illuminate\Support\Facades\DB;

class TrainingSessionProgressService
{
    public function markExerciseCompleted(TrainingProgramSlotExercise $exercise): void
    {
        DB::transaction(function () use ($exercise): void {
            $exercise->loadMissing('slot', 'sets');

            $now = now();
            $setCount = $exercise->sets->count();

            $exercise->sets()->update([
                'status' => TrainingProgramSlotSetStatusEnum::Completed->value,
                'has_any_modification' => false,
                'completed_at' => $now,
                'skipped_at' => null,
            ]);

            $exercise->forceFill([
                'status' => TrainingProgramSlotExerciseStatusEnum::Completed,
                'completed_set_count' => $setCount,
                'modified_set_count' => 0,
                'skipped_set_count' => 0,
                'pending_set_count' => 0,
                'has_any_modification' => false,
                'completed_at' => $now,
            ])->save();

            $this->recalculateSlotStatus($exercise->slot->fresh('exercises'));
        });
    }

    public function markExerciseSkipped(TrainingProgramSlotExercise $exercise): void
    {
        DB::transaction(function () use ($exercise): void {
            $exercise->loadMissing('slot', 'sets');

            $now = now();
            $setCount = $exercise->sets->count();

            $exercise->sets()->update([
                'status' => TrainingProgramSlotSetStatusEnum::Skipped->value,
                'has_any_modification' => false,
                'completed_at' => null,
                'skipped_at' => $now,
            ]);

            $exercise->forceFill([
                'status' => TrainingProgramSlotExerciseStatusEnum::Skipped,
                'completed_set_count' => 0,
                'modified_set_count' => 0,
                'skipped_set_count' => $setCount,
                'pending_set_count' => 0,
                'has_any_modification' => false,
                'completed_at' => null,
            ])->save();

            $this->recalculateSlotStatus($exercise->slot->fresh('exercises'));
        });
    }

    private function recalculateSlotStatus(TrainingProgramSlot $slot): void
    {
        $exerciseCount = $slot->exercises->count();
        $completed = $slot->exercises->where('status', TrainingProgramSlotExerciseStatusEnum::Completed)->count();
        $partial = $slot->exercises->where('status', TrainingProgramSlotExerciseStatusEnum::PartiallyCompleted)->count();
        $skipped = $slot->exercises->where('status', TrainingProgramSlotExerciseStatusEnum::Skipped)->count();
        $pending = $slot->exercises->where('status', TrainingProgramSlotExerciseStatusEnum::Pending)->count();

        $status = match (true) {
            $exerciseCount === 0 => TrainingProgramSlotStatusEnum::Pending,
            $pending === $exerciseCount => TrainingProgramSlotStatusEnum::Pending,
            $skipped === $exerciseCount => TrainingProgramSlotStatusEnum::Skipped,
            $pending === 0 && $partial === 0 && ($completed + $skipped) === $exerciseCount && $completed > 0 => TrainingProgramSlotStatusEnum::Completed,
            default => TrainingProgramSlotStatusEnum::PartiallyCompleted,
        };

        $slot->forceFill([
            'status' => $status,
            'exercise_count' => $exerciseCount,
            'completed_exercise_count' => $completed,
            'partial_exercise_count' => $partial,
            'skipped_exercise_count' => $skipped,
            'pending_exercise_count' => $pending,
            'has_any_modification' => false,
            'completed_at' => in_array($status, [TrainingProgramSlotStatusEnum::Completed, TrainingProgramSlotStatusEnum::Skipped], true)
                ? now()
                : null,
        ])->save();
    }
}
