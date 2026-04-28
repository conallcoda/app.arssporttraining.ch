<?php

namespace App\Observers;

use App\Models\Exercise\ExerciseProgram;
use App\Training\TrainingSessionRebuildDispatcher;

class ExerciseProgramObserver
{
    public function saved(ExerciseProgram $program): void
    {
        app(TrainingSessionRebuildDispatcher::class)
            ->dispatchFutureSlotsForExerciseProgramChange($program->id);
    }
}
