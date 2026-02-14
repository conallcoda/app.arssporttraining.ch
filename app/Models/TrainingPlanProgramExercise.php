<?php

namespace App\Models;

use App\Models\Exercise\Exercise;
use Coda\Cms\Models\Concerns\HasConfigData;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TrainingPlanProgramExercise extends Pivot
{
    use HasConfigData;

    protected $table = 'training_plan_program_exercises';

    public $incrementing = true;

    protected $fillable = [
        'training_plan_program_id',
        'exercise_id',
        'sort',
        'config',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingPlanProgram::class, 'training_plan_program_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
