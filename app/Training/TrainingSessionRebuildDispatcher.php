<?php

namespace App\Training;

use App\Jobs\RebuildFutureSlotsForAthleteExerciseProgramJob;
use App\Jobs\RebuildFutureSlotsForAthleteJob;
use App\Jobs\RebuildFutureSlotsForExerciseProgramJob;

class TrainingSessionRebuildDispatcher
{
    public function dispatchFutureSlotsForExerciseProgramChange(int $exerciseProgramId, ?int $userId = null): void
    {
        if ($userId !== null) {
            $this->dispatchFutureSlotsForAthleteExerciseProgram($userId, $exerciseProgramId);

            return;
        }

        $this->dispatchFutureSlotsForExerciseProgram($exerciseProgramId);
    }

    public function dispatchFutureSlotsForExerciseProgram(int $exerciseProgramId): void
    {
        dispatch_sync(new RebuildFutureSlotsForExerciseProgramJob($exerciseProgramId));
    }

    public function dispatchFutureSlotsForAthleteExerciseProgram(int $userId, int $exerciseProgramId): void
    {
        dispatch_sync(new RebuildFutureSlotsForAthleteExerciseProgramJob($userId, $exerciseProgramId));
    }

    public function dispatchFutureSlotsForAthlete(int $userId, ?string $fromDate = null): void
    {
        dispatch_sync(new RebuildFutureSlotsForAthleteJob($userId, $fromDate));
    }
}
