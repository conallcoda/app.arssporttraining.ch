<?php

namespace App\Training;

use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use Illuminate\Database\Eloquent\Builder;

class TrainingSessionEditGuard
{
    /**
     * Marks slots as immutable only once they are in the past and have some recorded outcome.
     */
    public function applyImmutableSlotConstraints(Builder $query): Builder
    {
        return $query
            ->where('datetime', '<=', now())
            ->where(function (Builder $query): void {
                $query
                    ->whereIn('status', [
                        TrainingProgramSlotStatusEnum::Completed,
                        TrainingProgramSlotStatusEnum::PartiallyCompleted,
                        TrainingProgramSlotStatusEnum::Skipped,
                    ])
                    ->orWhereNotNull('completed_at')
                    ->orWhere('has_any_modification', true)
                    ->orWhere('completed_exercise_count', '>', 0)
                    ->orWhere('partial_exercise_count', '>', 0)
                    ->orWhere('skipped_exercise_count', '>', 0);
            });
    }

    public function hasImmutableSlotsForOccurrence(
        int $trainingProgramId,
        string $datetime,
        ?int $userId = null,
        array $userIds = [],
    ): bool {
        return $this->countImmutableSlotsForOccurrence($trainingProgramId, $datetime, $userId, $userIds) > 0;
    }

    public function countImmutableSlotsForOccurrence(
        int $trainingProgramId,
        string $datetime,
        ?int $userId = null,
        array $userIds = [],
    ): int {
        return $this->immutableSlotsQuery()
            ->where('training_program_id', $trainingProgramId)
            ->where('datetime', $datetime)
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($userId === null && ! empty($userIds), fn (Builder $query) => $query->whereIn('user_id', $userIds))
            ->count();
    }

    public function hasImmutableSlotsForTrainingProgram(int $trainingProgramId): bool
    {
        return $this->countImmutableSlotsForTrainingProgram($trainingProgramId) > 0;
    }

    public function countImmutableSlotsForTrainingProgram(int $trainingProgramId): int
    {
        return $this->immutableSlotsQuery()
            ->where('training_program_id', $trainingProgramId)
            ->count();
    }

    public function hasImmutableSlotsForExerciseProgram(int $exerciseProgramId): bool
    {
        return $this->countImmutableSlotsForExerciseProgram($exerciseProgramId) > 0;
    }

    public function countImmutableSlotsForExerciseProgram(int $exerciseProgramId): int
    {
        return $this->immutableSlotsQuery()
            ->whereHas('trainingProgram', fn (Builder $query) => $query->where('exercise_program_id', $exerciseProgramId))
            ->count();
    }

    public function immutableSlotMessage(int $count = 1): string
    {
        return trans_choice(
            'This past session already has recorded data and can no longer be edited.|:count past sessions already have recorded data and can no longer be edited.',
            $count,
            ['count' => $count],
        );
    }

    public function immutableProgramMessage(int $count): string
    {
        return trans_choice(
            'This program cannot be changed here because 1 past session already has recorded data.|This program cannot be changed here because :count past sessions already have recorded data.',
            $count,
            ['count' => $count],
        );
    }

    private function immutableSlotsQuery(): Builder
    {
        return $this->applyImmutableSlotConstraints(TrainingProgramSlot::query());
    }
}
