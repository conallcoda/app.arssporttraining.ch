<?php

namespace App\Models;

use App\Data\Training\Config\PlanExerciseConfig;
use App\Models\Exercise\Exercise;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TrainingPlanProgramExercise extends Pivot
{
    protected $table = 'training_plan_program_exercises';

    public $incrementing = true;

    protected $fillable = [
        'training_plan_program_id',
        'exercise_id',
        'sort',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => PlanExerciseConfig::class,
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingPlanProgram::class, 'training_plan_program_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
