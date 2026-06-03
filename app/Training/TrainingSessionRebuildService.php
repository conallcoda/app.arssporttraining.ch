<?php

namespace App\Training;

use App\Models\Training\TrainingProgramSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class TrainingSessionRebuildService
{
    public function __construct(
        private readonly TrainingSessionMaterializer $materializer,
    ) {}

    public function rebuildFutureSlotsForExerciseProgram(int $exerciseProgramId): void
    {
        $this->rebuildFutureSlots(
            $this->futureSlotsQuery()
                ->whereHas('trainingProgram', fn (Builder $query) => $query->where('exercise_program_id', $exerciseProgramId))
        );
    }

    public function rebuildFutureSlotsForAthleteExerciseProgram(int $userId, int $exerciseProgramId): void
    {
        $this->rebuildFutureSlots(
            $this->futureSlotsQuery()
                ->where('user_id', $userId)
                ->whereHas('trainingProgram', fn (Builder $query) => $query->where('exercise_program_id', $exerciseProgramId))
        );
    }

    public function rebuildFutureSlotsForTrainingProgram(int $trainingProgramId): void
    {
        $this->rebuildFutureSlots(
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

        $this->rebuildFutureSlots(
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

        $this->rebuildFutureSlots(
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

    private function rebuildFutureSlots(Builder $query): void
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
}
