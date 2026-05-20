<?php

namespace App\Observers;

use App\Models\Exercise\ExerciseProgramExercise;
use App\Support\Training\ExerciseProgramSelectorPreviewService;
use App\Training\TrainingSessionRebuildDispatcher;

class ExerciseProgramExerciseObserver
{
    public function saved(ExerciseProgramExercise $pivot): void
    {
        app(ExerciseProgramSelectorPreviewService::class)
            ->syncProgram($pivot->exercise_program_id);

        app(TrainingSessionRebuildDispatcher::class)
            ->dispatchFutureSlotsForExerciseProgramChange($pivot->exercise_program_id);
    }

    public function deleted(ExerciseProgramExercise $pivot): void
    {
        app(ExerciseProgramSelectorPreviewService::class)
            ->syncProgram($pivot->exercise_program_id);

        app(TrainingSessionRebuildDispatcher::class)
            ->dispatchFutureSlotsForExerciseProgramChange($pivot->exercise_program_id);
    }
}
