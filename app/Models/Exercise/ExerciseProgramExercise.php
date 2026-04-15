<?php

namespace App\Models\Exercise;

use App\Observers\ExerciseProgramExerciseObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ExerciseProgramExercise extends Pivot
{
    protected $table = 'exercise_program_exercises';

    public $incrementing = true;

    protected $fillable = [
        'exercise_program_id',
        'exercise_id',
        'sort',
        'group',
        'type',
    ];

    public function exerciseProgram(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgram::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    protected static function booted(): void
    {
        static::observe(ExerciseProgramExerciseObserver::class);
    }
}
