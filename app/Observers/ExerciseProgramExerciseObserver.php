<?php

namespace App\Observers;

use App\Models\Exercise\ExerciseProgramExercise;
use App\Training\TrainingSessionRebuildDispatcher;

class ExerciseProgramExerciseObserver
{
    public function saved(ExerciseProgramExercise $pivot): void
    {
        app(TrainingSessionRebuildDispatcher::class)
            ->dispatchFutureSlotsForExerciseProgramChange($pivot->exercise_program_id);
    }

    public function deleted(ExerciseProgramExercise $pivot): void
    {
        app(TrainingSessionRebuildDispatcher::class)
            ->dispatchFutureSlotsForExerciseProgramChange($pivot->exercise_program_id);
    }
}
