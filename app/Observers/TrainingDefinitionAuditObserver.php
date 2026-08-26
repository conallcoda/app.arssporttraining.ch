<?php

namespace App\Observers;

use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Training\TrainingModelAuditService;
use Illuminate\Database\Eloquent\Model;

class TrainingDefinitionAuditObserver
{
    public function created(Model $model): void
    {
        app(TrainingModelAuditService::class)->recordCreated($model, 'definition', $this->subjectName($model));
    }

    public function updated(Model $model): void
    {
        app(TrainingModelAuditService::class)->recordUpdated($model, 'definition', $this->subjectName($model));
    }

    public function deleted(Model $model): void
    {
        app(TrainingModelAuditService::class)->recordDeleted($model, 'definition', $this->subjectName($model));
    }

    private function subjectName(Model $model): string
    {
        return match ($model::class) {
            ExerciseProgram::class => 'exercise_program',
            ExerciseProgramExercise::class => 'program_exercise',
            TrainingProgram::class => 'training_program',
            TrainingProgramBlock::class => 'training_program_block',
            default => 'training_definition',
        };
    }
}
