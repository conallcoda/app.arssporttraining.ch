<?php

namespace App\Models;

use App\Models\Exercise\Exercise;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExerciseProgram extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'program_category_id',
        'sort',
    ];

    public function programCategory(): BelongsTo
    {
        return $this->belongsTo(ProgramCategory::class);
    }

    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'exercise_program_exercises')
            ->using(ExerciseProgramExercise::class)
            ->withPivot(['id', 'sort'])
            ->orderByPivot('sort')
            ->withTimestamps();
    }
}
