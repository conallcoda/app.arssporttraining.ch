<?php

namespace App\Observers;

use App\Models\Exercise\ExerciseProgramExercise;
use App\Training\TrainingSessionRebuildService;

class ExerciseProgramExerciseObserver
{
    public function saved(ExerciseProgramExercise $pivot): void
    {
        app(TrainingSessionRebuildService::class)
            ->rebuildFutureSlotsForExerciseProgram($pivot->exercise_program_id);
    }

    public function deleted(ExerciseProgramExercise $pivot): void
    {
        app(TrainingSessionRebuildService::class)
            ->rebuildFutureSlotsForExerciseProgram($pivot->exercise_program_id);
    }
}
