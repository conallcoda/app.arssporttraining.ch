<?php

namespace App\Training;

use App\Models\Training\TrainingProgramSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TrainingSessionRebuildService
{
    public function __construct(
        private readonly TrainingSessionMaterializer $materializer,
    ) {}

    public function rebuildFutureSlotsForExerciseProgram(int $exerciseProgramId): void
    {
        $this->futureSlotsQuery()
            ->whereHas('trainingProgram', fn (Builder $query) => $query->where('exercise_program_id', $exerciseProgramId))
            ->each(fn (TrainingProgramSlot $slot) => $this->materializer->materialize($slot, force: true));
    }

    public function rebuildFutureSlotsForAthlete(int $userId, ?string $fromDate = null): void
    {
        $threshold = Carbon::parse($fromDate ?? now()->toDateString())->startOfDay();
        $now = now();
        if ($threshold->lt($now)) {
            $threshold = $now;
        }

        $this->futureSlotsQuery()
            ->where('user_id', $userId)
            ->where('datetime', '>', $threshold)
            ->each(fn (TrainingProgramSlot $slot) => $this->materializer->materialize($slot, force: true));
    }

    private function futureSlotsQuery(): Builder
    {
        return TrainingProgramSlot::query()
            ->whereNull('cancelled_at')
            ->where('datetime', '>', now())
            ->orderBy('datetime')
            ->orderBy('id');
    }
}
