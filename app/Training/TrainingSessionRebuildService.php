<?php

namespace App\Training;

use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Users\UserGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class TrainingSessionRebuildService
{
    public function __construct(
        private readonly TrainingSessionMaterializer $materializer,
        private readonly TrainingSessionEditGuard $editGuard,
    ) {}

    public function rebuildOpenSlotsForExerciseProgram(int $exerciseProgramId, ?string $fromDate = null): void
    {
        $this->rebuildSlots(
            $this->openSlotsQuery()
                ->whereHas('trainingProgram', fn (Builder $query) => $query->where('exercise_program_id', $exerciseProgramId))
                ->when($fromDate !== null, fn (Builder $query) => $this->whereOnOrAfterDate($query, $fromDate))
        );
    }

    public function rebuildOpenSlotsForAthleteExerciseProgram(int $userId, int $exerciseProgramId, ?string $fromDate = null): void
    {
        $this->rebuildSlots(
            $this->openSlotsQuery()
                ->where('user_id', $userId)
                ->whereHas('trainingProgram', fn (Builder $query) => $query->where('exercise_program_id', $exerciseProgramId))
                ->when($fromDate !== null, fn (Builder $query) => $this->whereOnOrAfterDate($query, $fromDate))
        );
    }

    public function rebuildOpenSlotsForTrainingProgram(int $trainingProgramId, ?string $fromDate = null): void
    {
        $this->rebuildSlots(
            $this->openSlotsQuery()
                ->where('training_program_id', $trainingProgramId)
                ->when($fromDate !== null, fn (Builder $query) => $this->whereOnOrAfterDate($query, $fromDate))
        );
    }

    public function rebuildOpenSlotsForTrainingProgramAthlete(int $trainingProgramId, int $userId, ?string $fromDate = null): void
    {
        $this->rebuildSlots(
            $this->openSlotsQuery()
                ->where('training_program_id', $trainingProgramId)
                ->where('user_id', $userId)
                ->when($fromDate !== null, fn (Builder $query) => $this->whereOnOrAfterDate($query, $fromDate))
        );
    }

    public function rebuildOpenSlotsForAthlete(int $userId, ?string $fromDate = null): void
    {
        $this->rebuildSlots(
            $this->openSlotsQuery()
                ->where('user_id', $userId)
                ->when($fromDate !== null, fn (Builder $query) => $this->whereOnOrAfterDate($query, $fromDate))
        );
    }

    public function rebuildOpenSlotsForCategoryBlock(TrainingProgramBlock $block): void
    {
        if ($block->category_id === null || $block->start === null) {
            return;
        }

        $athleteIds = $this->athleteIdsForBlock($block);

        if ($athleteIds === []) {
            return;
        }

        $start = $block->start->copy()->startOfDay();
        $end = ($block->end ?? $block->start)->copy()->endOfDay();

        $this->rebuildSlots(
            $this->openSlotsQuery()
                ->whereIn('user_id', $athleteIds)
                ->whereBetween('datetime', [$start, $end])
                ->whereHas('trainingProgram', function (Builder $query) use ($block): void {
                    $query
                        ->where('group_id', $block->group_id)
                        ->whereHas('program', fn (Builder $programQuery) => $programQuery
                            ->where('exercise_category_id', $block->category_id));
                })
        );
    }

    public function rebuildFutureSlotsForExerciseProgram(int $exerciseProgramId, ?string $fromDate = null): void
    {
        $this->rebuildSlots(
            $this->futureSlotsQuery()
                ->whereHas('trainingProgram', fn (Builder $query) => $query->where('exercise_program_id', $exerciseProgramId))
                ->when($fromDate !== null, fn (Builder $query) => $this->whereOnOrAfterFromDate($query, $fromDate))
        );
    }

    public function rebuildFutureSlotsForAthleteExerciseProgram(int $userId, int $exerciseProgramId, ?string $fromDate = null): void
    {
        $this->rebuildSlots(
            $this->futureSlotsQuery()
                ->where('user_id', $userId)
                ->whereHas('trainingProgram', fn (Builder $query) => $query->where('exercise_program_id', $exerciseProgramId))
                ->when($fromDate !== null, fn (Builder $query) => $this->whereOnOrAfterFromDate($query, $fromDate))
        );
    }

    public function rebuildFutureSlotsForTrainingProgram(int $trainingProgramId): void
    {
        $this->rebuildSlots(
            $this->futureSlotsQuery()
                ->where('training_program_id', $trainingProgramId)
        );
    }

    public function rebuildFutureSlotsForTrainingProgramAthlete(int $trainingProgramId, int $userId, ?string $fromDate = null): void
    {
        $threshold = Carbon::parse($fromDate ?? now()->toDateString())->startOfDay();
        $now = now();
        if ($threshold->lt($now)) {
            $threshold = $now;
        }

        $this->rebuildSlots(
            $this->futureSlotsQuery()
                ->where('training_program_id', $trainingProgramId)
                ->where('user_id', $userId)
                ->where('datetime', '>=', $threshold)
        );
    }

    public function rebuildFutureSlotsForAthlete(int $userId, ?string $fromDate = null): void
    {
        $threshold = Carbon::parse($fromDate ?? now()->toDateString())->startOfDay();
        $now = now();
        if ($threshold->lt($now)) {
            $threshold = $now;
        }

        $this->rebuildSlots(
            $this->futureSlotsQuery()
                ->where('user_id', $userId)
                ->where('datetime', '>', $threshold)
        );
    }

    private function futureSlotsQuery(): Builder
    {
        return TrainingProgramSlot::query()
            ->whereNull('cancelled_at')
            ->where('datetime', '>', now())
            ->orderBy('datetime')
            ->orderBy('id');
    }

    private function openSlotsQuery(): Builder
    {
        return TrainingProgramSlot::query()
            ->whereNull('cancelled_at')
            ->whereNot(fn (Builder $query) => $this->editGuard->applyImmutableSlotConstraints($query))
            ->orderBy('datetime')
            ->orderBy('id');
    }

    private function whereOnOrAfterFromDate(Builder $query, string $fromDate): Builder
    {
        $threshold = Carbon::parse($fromDate)->startOfDay();
        $now = now();
        if ($threshold->lt($now)) {
            $threshold = $now;
        }

        return $query->where('datetime', '>=', $threshold);
    }

    private function whereOnOrAfterDate(Builder $query, string $fromDate): Builder
    {
        return $query->where('datetime', '>=', Carbon::parse($fromDate)->startOfDay());
    }

    private function rebuildSlots(Builder $query): void
    {
        $query
            ->with([
                'trainingProgram.program.exercises' => fn ($relation) => $relation
                    ->orderByPivot('type')
                    ->orderByPivot('sort')
                    ->orderByPivot('id'),
            ])
            ->chunk(100, function (Collection $slots): void {
                $slots->each(fn (TrainingProgramSlot $slot) => $this->materializer->materialize($slot, force: true));
            });
    }

    /** @return list<int> */
    private function athleteIdsForBlock(TrainingProgramBlock $block): array
    {
        if ($block->user_id !== null) {
            return [(int) $block->user_id];
        }

        return UserGroup::query()
            ->with('members:id')
            ->find($block->group_id)
            ?->members
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all() ?? [];
    }
}
