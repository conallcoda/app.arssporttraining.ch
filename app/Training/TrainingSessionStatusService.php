<?php

namespace App\Training;

use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Training\TrainingProgramSlotSetValue;

class TrainingSessionStatusService
{
    public function __construct(
        private readonly TrainingValueSnapshotCodec $valueCodec,
    ) {}

    public function refreshExerciseState(TrainingProgramSlotExercise $exercise): void
    {
        $exercise = $exercise->fresh(['slot', 'sets.values']) ?? $exercise;
        $exercise->loadMissing('sets.values', 'slot.exercises');

        foreach ($exercise->sets as $set) {
            $this->syncSetValueModificationFlags($set);
            $this->persistSetAggregate($set->fresh('values') ?? $set);
        }

        $this->persistExerciseAggregate($exercise->fresh(['slot', 'sets.values']) ?? $exercise);
    }

    public function recalculateSet(TrainingProgramSlotSet $set): void
    {
        $set = $set->fresh('values') ?? $set;
        $set->loadMissing('values');
        $this->syncSetValueModificationFlags($set);
        $this->persistSetAggregate($set->fresh('values') ?? $set);
    }

    public function recalculateExercise(TrainingProgramSlotExercise $exercise): void
    {
        $exercise = $exercise->fresh(['slot', 'sets.values']) ?? $exercise;
        $exercise->loadMissing('sets.values', 'slot.exercises');

        foreach ($exercise->sets as $set) {
            $this->recalculateSet($set);
        }

        $this->persistExerciseAggregate($exercise->fresh(['slot', 'sets.values']) ?? $exercise);
    }

    public function recalculateSlot(TrainingProgramSlot $slot): void
    {
        $slot->loadMissing('exercises');

        $exerciseCount = $slot->exercises->count();
        $completed = $slot->exercises->where('status', TrainingProgramSlotExerciseStatusEnum::Completed)->count();
        $partial = $slot->exercises->where('status', TrainingProgramSlotExerciseStatusEnum::PartiallyCompleted)->count();
        $skipped = $slot->exercises->where('status', TrainingProgramSlotExerciseStatusEnum::Skipped)->count();
        $pending = $slot->exercises->where('status', TrainingProgramSlotExerciseStatusEnum::Pending)->count();
        $hasAnyModification = $slot->exercises->contains(fn (TrainingProgramSlotExercise $exercise): bool => (bool) $exercise->has_any_modification);

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
            'has_any_modification' => $hasAnyModification,
            'completed_at' => in_array($status, [TrainingProgramSlotStatusEnum::Completed, TrainingProgramSlotStatusEnum::Skipped], true)
                ? now()
                : null,
        ])->save();
    }

    private function syncSetValueModificationFlags(TrainingProgramSlotSet $set): void
    {
        foreach ($set->values as $valueRow) {
            $isModified = $this->resolveValueIsModified($valueRow);

            if ((bool) $valueRow->is_modified === $isModified) {
                continue;
            }

            $valueRow->forceFill([
                'is_modified' => $isModified,
            ])->save();
        }
    }

    private function resolveValueIsModified(TrainingProgramSlotSetValue $valueRow): bool
    {
        if ($this->valueCodec->extractActualType($valueRow) === null) {
            return false;
        }

        return ! $this->valuesEquivalent(
            $this->valueCodec->extractActualValue($valueRow),
            $this->valueCodec->extractPlannedValue($valueRow),
        );
    }

    private function valuesEquivalent(mixed $left, mixed $right): bool
    {
        if (is_float($left) || is_float($right)) {
            return (float) $left === (float) $right;
        }

        return $left === $right;
    }

    private function persistSetAggregate(TrainingProgramSlotSet $set): void
    {
        $hasAnyModification = $set->values->contains(fn ($value): bool => (bool) $value->is_modified);

        $status = match (true) {
            $set->skipped_at !== null => TrainingProgramSlotSetStatusEnum::Skipped,
            $set->completed_at !== null && $hasAnyModification => TrainingProgramSlotSetStatusEnum::CompletedWithModification,
            $set->completed_at !== null => TrainingProgramSlotSetStatusEnum::Completed,
            default => TrainingProgramSlotSetStatusEnum::Pending,
        };

        $set->forceFill([
            'status' => $status,
            'has_any_modification' => $hasAnyModification,
        ])->save();
    }

    private function persistExerciseAggregate(TrainingProgramSlotExercise $exercise): void
    {
        $sets = $exercise->sets;
        $setCount = $sets->count();
        $completed = $sets->filter(fn (TrainingProgramSlotSet $set): bool => in_array($set->status, [
            TrainingProgramSlotSetStatusEnum::Completed,
            TrainingProgramSlotSetStatusEnum::CompletedWithModification,
        ], true))->count();
        $skipped = $sets->where('status', TrainingProgramSlotSetStatusEnum::Skipped)->count();
        $pending = $sets->where('status', TrainingProgramSlotSetStatusEnum::Pending)->count();
        $modified = $sets->filter(fn (TrainingProgramSlotSet $set): bool => (bool) $set->has_any_modification)->count();
        $hasAnyModification = $modified > 0;

        $status = match (true) {
            $setCount === 0 => TrainingProgramSlotExerciseStatusEnum::Pending,
            $pending === $setCount => TrainingProgramSlotExerciseStatusEnum::Pending,
            $skipped === $setCount => TrainingProgramSlotExerciseStatusEnum::Skipped,
            $pending === 0 && ($completed + $skipped) === $setCount && $completed > 0 => TrainingProgramSlotExerciseStatusEnum::Completed,
            default => TrainingProgramSlotExerciseStatusEnum::PartiallyCompleted,
        };

        $exercise->forceFill([
            'status' => $status,
            'set_count' => $setCount,
            'completed_set_count' => $completed,
            'modified_set_count' => $modified,
            'skipped_set_count' => $skipped,
            'pending_set_count' => $pending,
            'has_any_modification' => $hasAnyModification,
            'completed_at' => $status === TrainingProgramSlotExerciseStatusEnum::Completed
                ? ($sets->pluck('completed_at')->filter()->max() ?? now())
                : null,
        ])->save();

        $this->recalculateSlot($exercise->slot->fresh('exercises'));
    }
}
