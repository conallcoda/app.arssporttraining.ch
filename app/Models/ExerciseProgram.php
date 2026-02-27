<?php

namespace App\Models;

use App\Data\Training\Config\ExercisePlanConfig;
use App\Models\Exercise\Exercise;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExerciseProgram extends Model
{
    use HasFactory;
    use HasQueryBuilder;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'program_category_id',
        'sort',
        'config',
    ];

    protected function config(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value
                ? ExercisePlanConfig::from(json_decode($value, true))
                : ExercisePlanConfig::initialize(),
            set: fn (ExercisePlanConfig|array $value) => json_encode(
                $value instanceof ExercisePlanConfig ? $value->toArray() : $value
            ),
        );
    }

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
