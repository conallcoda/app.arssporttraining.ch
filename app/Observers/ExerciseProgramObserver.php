<?php

namespace App\Observers;

use App\Models\Exercise\ExerciseProgram;
use App\Training\TrainingSessionRebuildService;

class ExerciseProgramObserver
{
    public function saved(ExerciseProgram $program): void
    {
        app(TrainingSessionRebuildService::class)
            ->rebuildFutureSlotsForExerciseProgram($program->id);
    }
}
