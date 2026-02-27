<?php

namespace App\Models;

use App\Models\Exercise\Exercise;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ExercisePlanProgramExercise extends Pivot
{
    protected $table = 'exercise_plan_program_exercises';

    public $incrementing = true;

    protected $fillable = [
        'exercise_plan_program_id',
        'exercise_id',
        'sort',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(ExercisePlanProgram::class, 'exercise_plan_program_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
