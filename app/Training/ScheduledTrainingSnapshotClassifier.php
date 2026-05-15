<?php

namespace App\Training;

use App\Data\Training\Audit\ScheduledSnapshotClassificationData;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotStatusEnum;

class ScheduledTrainingSnapshotClassifier
{
    public function classify(TrainingProgramSlot $slot): ScheduledSnapshotClassificationData
    {
        $slot->loadMissing('exercises.sets.values');

        $reasons = [];

        if ($slot->datetime->lte(now())) {
            $reasons[] = 'datetime_in_past';
        }

        if (in_array($slot->status, [
            TrainingProgramSlotStatusEnum::Completed,
            TrainingProgramSlotStatusEnum::PartiallyCompleted,
            TrainingProgramSlotStatusEnum::Skipped,
        ], true)) {
            $reasons[] = 'non_pending_status';
        }

        if ($slot->completed_at !== null) {
            $reasons[] = 'completed_at_present';
        }

        if ($slot->cancelled_at !== null) {
            $reasons[] = 'cancelled';
        }

        if ($this->hasRecordedActuals($slot)) {
            $reasons[] = 'actual_values_present';
        }

        if ((bool) $slot->has_any_modification) {
            $reasons[] = 'slot_modified';
        }

        if ($this->hasExerciseOrSetModifications($slot)) {
            $reasons[] = 'exercise_or_set_modified';
        }

        if ($this->hasNonPendingExerciseOrSetStatuses($slot)) {
            $reasons[] = 'exercise_or_set_status_not_pending';
        }

        $reasons = array_values(array_unique($reasons));

        if (array_intersect($reasons, ['datetime_in_past', 'non_pending_status', 'completed_at_present'])) {
            return new ScheduledSnapshotClassificationData('locked_past', $reasons);
        }

        if ($reasons === []) {
            return new ScheduledSnapshotClassificationData('future_open', []);
        }

        return new ScheduledSnapshotClassificationData('ambiguous_boundary', $reasons);
    }

    private function hasRecordedActuals(TrainingProgramSlot $slot): bool
    {
        return $slot->exercises->contains(function (TrainingProgramSlotExercise $exercise): bool {
            return $exercise->sets->contains(function (TrainingProgramSlotSet $set): bool {
                return $set->values->contains(fn ($value): bool => $value->actual_value_type !== null);
            });
        });
    }

    private function hasExerciseOrSetModifications(TrainingProgramSlot $slot): bool
    {
        return $slot->exercises->contains(function (TrainingProgramSlotExercise $exercise): bool {
            if ((bool) $exercise->has_any_modification) {
                return true;
            }

            return $exercise->sets->contains(fn (TrainingProgramSlotSet $set): bool => (bool) $set->has_any_modification);
        });
    }

    private function hasNonPendingExerciseOrSetStatuses(TrainingProgramSlot $slot): bool
    {
        return $slot->exercises->contains(function (TrainingProgramSlotExercise $exercise): bool {
            if ((string) ($exercise->status->value ?? $exercise->status) !== 'pending') {
                return true;
            }

            return $exercise->sets->contains(
                fn (TrainingProgramSlotSet $set): bool => (string) ($set->status->value ?? $set->status) !== 'pending'
            );
        });
    }
}
