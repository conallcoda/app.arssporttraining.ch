<?php

namespace App\Training;

use App\Jobs\RebuildFutureSlotsForAthleteExerciseProgramJob;
use App\Jobs\RebuildFutureSlotsForAthleteJob;
use App\Jobs\RebuildFutureSlotsForExerciseProgramJob;
use App\Jobs\RebuildFutureSlotsForTrainingProgramAthleteJob;
use App\Jobs\RebuildFutureSlotsForTrainingProgramJob;

class TrainingSessionRebuildDispatcher
{
    public function dispatchOpenSlotsForExerciseProgramChange(int $exerciseProgramId, ?int $userId = null, ?string $fromDate = null): void
    {
        if ($userId !== null) {
            $this->dispatchOpenSlotsForAthleteExerciseProgram($userId, $exerciseProgramId, $fromDate);

            return;
        }

        $this->dispatchOpenSlotsForExerciseProgram($exerciseProgramId, $fromDate);
    }

    public function dispatchOpenSlotsForExerciseProgram(int $exerciseProgramId, ?string $fromDate = null): void
    {
        app(TrainingSessionRebuildService::class)->rebuildOpenSlotsForExerciseProgram($exerciseProgramId, $fromDate);
    }

    public function dispatchOpenSlotsForAthleteExerciseProgram(int $userId, int $exerciseProgramId, ?string $fromDate = null): void
    {
        app(TrainingSessionRebuildService::class)->rebuildOpenSlotsForAthleteExerciseProgram($userId, $exerciseProgramId, $fromDate);
    }

    public function dispatchOpenSlotsForTrainingProgramChange(int $trainingProgramId, ?int $userId = null, ?string $fromDate = null): void
    {
        if ($userId !== null) {
            app(TrainingSessionRebuildService::class)->rebuildOpenSlotsForTrainingProgramAthlete($trainingProgramId, $userId, $fromDate);

            return;
        }

        app(TrainingSessionRebuildService::class)->rebuildOpenSlotsForTrainingProgram($trainingProgramId, $fromDate);
    }

    public function dispatchFutureSlotsForExerciseProgramChange(int $exerciseProgramId, ?int $userId = null, ?string $fromDate = null): void
    {
        if ($userId !== null) {
            $this->dispatchFutureSlotsForAthleteExerciseProgram($userId, $exerciseProgramId, $fromDate);

            return;
        }

        $this->dispatchFutureSlotsForExerciseProgram($exerciseProgramId, $fromDate);
    }

    public function dispatchFutureSlotsForExerciseProgram(int $exerciseProgramId, ?string $fromDate = null): void
    {
        dispatch_sync(new RebuildFutureSlotsForExerciseProgramJob($exerciseProgramId, $fromDate));
    }

    public function dispatchFutureSlotsForAthleteExerciseProgram(int $userId, int $exerciseProgramId, ?string $fromDate = null): void
    {
        dispatch_sync(new RebuildFutureSlotsForAthleteExerciseProgramJob($userId, $exerciseProgramId, $fromDate));
    }

    public function dispatchFutureSlotsForTrainingProgramChange(int $trainingProgramId, ?int $userId = null, ?string $fromDate = null): void
    {
        if ($userId !== null) {
            dispatch_sync(new RebuildFutureSlotsForTrainingProgramAthleteJob($trainingProgramId, $userId, $fromDate));

            return;
        }

        dispatch_sync(new RebuildFutureSlotsForTrainingProgramJob($trainingProgramId));
    }

    public function dispatchFutureSlotsForAthlete(int $userId, ?string $fromDate = null): void
    {
        dispatch_sync(new RebuildFutureSlotsForAthleteJob($userId, $fromDate));
    }
}
