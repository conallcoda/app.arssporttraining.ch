<?php

namespace App\Observers;

use App\Models\Exercise\Exercise;
use App\Support\Training\ExerciseProgramSelectorPreviewService;

class ExerciseObserver
{
    public function updated(Exercise $exercise): void
    {
        if (! $exercise->wasChanged(['name', 'deleted_at'])) {
            return;
        }

        app(ExerciseProgramSelectorPreviewService::class)
            ->syncProgramsForExercise($exercise->id);
    }

    public function deleted(Exercise $exercise): void
    {
        app(ExerciseProgramSelectorPreviewService::class)
            ->syncProgramsForExercise($exercise->id);
    }

    public function restored(Exercise $exercise): void
    {
        app(ExerciseProgramSelectorPreviewService::class)
            ->syncProgramsForExercise($exercise->id);
    }
}
