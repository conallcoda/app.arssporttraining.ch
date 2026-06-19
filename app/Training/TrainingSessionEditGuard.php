<?php

namespace App\Training;

use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TrainingSessionEditGuard
{
    /**
     * Plan edits are locked only once a scheduled slot has recorded data.
     */
    public function applyPlanEditLockConstraints(Builder $query): Builder
    {
        return $this->applyRecordedOutcomeConstraints($query);
    }

    /**
     * @return array<string, true>
     */
    public function planEditLockedDateTimeLookup(Builder $query): array
    {
        return $this->applyPlanEditLockConstraints($query)
            ->select('datetime')
            ->distinct()
            ->pluck('datetime')
            ->mapWithKeys(fn ($datetime): array => [Carbon::parse($datetime)->toDateTimeString() => true])
            ->all();
    }

    /**
     * Marks slots as immutable once they have some recorded outcome.
     */
    public function applyImmutableSlotConstraints(Builder $query): Builder
    {
        return $this->applyRecordedOutcomeConstraints($query);
    }

    public function isImmutableSlot(TrainingProgramSlot $slot): bool
    {
        if (! $slot->exists) {
            return false;
        }

        return $this->applyImmutableSlotConstraints(
            TrainingProgramSlot::query()->whereKey($slot->id),
        )->exists();
    }

    public function aggregateColumnsIndicateRecordedOutcome(TrainingProgramSlot $slot): bool
    {
        $status = $slot->status instanceof TrainingProgramSlotStatusEnum
            ? $slot->status
            : TrainingProgramSlotStatusEnum::tryFrom((string) ($slot->status ?? TrainingProgramSlotStatusEnum::Pending->value));

        return in_array($status, [
            TrainingProgramSlotStatusEnum::Completed,
            TrainingProgramSlotStatusEnum::PartiallyCompleted,
            TrainingProgramSlotStatusEnum::Skipped,
        ], true)
            || $slot->completed_at !== null
            || (bool) $slot->has_any_modification
            || (int) $slot->completed_exercise_count > 0
            || (int) $slot->partial_exercise_count > 0
            || (int) $slot->skipped_exercise_count > 0
            || (bool) ($slot->has_recorded_exercise_rows ?? false);
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
            'This session already has recorded data and can no longer be edited.|:count sessions already have recorded data and can no longer be edited.',
            $count,
            ['count' => $count],
        );
    }

    public function immutableProgramMessage(int $count): string
    {
        return trans_choice(
            'This program cannot be changed here because 1 session already has recorded data.|This program cannot be changed here because :count sessions already have recorded data.',
            $count,
            ['count' => $count],
        );
    }

    public function applyRecordedExerciseOutcomeConstraints(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->whereIn('status', [
                    TrainingProgramSlotExerciseStatusEnum::Completed,
                    TrainingProgramSlotExerciseStatusEnum::PartiallyCompleted,
                    TrainingProgramSlotExerciseStatusEnum::Skipped,
                ])
                ->orWhereNotNull('completed_at')
                ->orWhere('has_any_modification', true)
                ->orWhere('completed_set_count', '>', 0)
                ->orWhere('modified_set_count', '>', 0)
                ->orWhere('skipped_set_count', '>', 0)
                ->orWhereHas('sets', fn (Builder $query): Builder => $this->applyRecordedSetOutcomeConstraints($query));
        });
    }

    public function applyRecordedSetOutcomeConstraints(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->whereIn('status', [
                    TrainingProgramSlotSetStatusEnum::Completed,
                    TrainingProgramSlotSetStatusEnum::CompletedWithModification,
                    TrainingProgramSlotSetStatusEnum::Skipped,
                ])
                ->orWhereNotNull('completed_at')
                ->orWhereNotNull('skipped_at')
                ->orWhere('has_any_modification', true)
                ->orWhereHas('values', function (Builder $query): void {
                    $query
                        ->whereNotNull('actual_value_type')
                        ->orWhere('actual_is_explicit', true)
                        ->orWhere('is_modified', true);
                });
        });
    }

    private function applyRecordedOutcomeConstraints(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
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
                ->orWhere('skipped_exercise_count', '>', 0)
                ->orWhereHas('exercises', fn (Builder $query): Builder => $this->applyRecordedExerciseOutcomeConstraints($query));
        });
    }

    private function immutableSlotsQuery(): Builder
    {
        return $this->applyImmutableSlotConstraints(TrainingProgramSlot::query());
    }
}
