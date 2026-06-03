<?php

namespace App\Training;

use App\Jobs\RebuildFutureSlotsForAthleteExerciseProgramJob;
use App\Jobs\RebuildFutureSlotsForAthleteJob;
use App\Jobs\RebuildFutureSlotsForExerciseProgramJob;
use App\Jobs\RebuildFutureSlotsForTrainingProgramAthleteJob;
use App\Jobs\RebuildFutureSlotsForTrainingProgramJob;

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

    public function dispatchFutureSlotsForTrainingProgramChange(int $trainingProgramId, ?int $userId = null): void
    {
        if ($userId !== null) {
            dispatch_sync(new RebuildFutureSlotsForTrainingProgramAthleteJob($trainingProgramId, $userId));

            return;
        }

        dispatch_sync(new RebuildFutureSlotsForTrainingProgramJob($trainingProgramId));
    }

    public function dispatchFutureSlotsForAthlete(int $userId, ?string $fromDate = null): void
    {
        dispatch_sync(new RebuildFutureSlotsForAthleteJob($userId, $fromDate));
    }
}
