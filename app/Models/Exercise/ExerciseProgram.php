<?php

namespace App\Models\Exercise;

use App\Data\Training\Config\ExercisePlanConfig;
use App\Models\Concerns\HasOwner;
use App\Models\Tag;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Coda\Cms\Models\Concerns\HasTags;
use Coda\Cms\Models\Contracts\Taggable;
use Database\Factories\ExerciseProgramFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExerciseProgram extends Model implements Taggable
{
    /** @use HasFactory<ExerciseProgramFactory> */
    use HasFactory;

    use HasOwner;
    use HasQueryBuilder;
    use HasTags;
    use SoftDeletes;

    protected static function newFactory(): ExerciseProgramFactory
    {
        return ExerciseProgramFactory::new();
    }

    protected $fillable = [
        'name',
        'exercise_category_id',
        'sort',
        'config',
        'parent_type',
        'parent_id',
        'owner_id',
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

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function exerciseCategory(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'exercise_category_id');
    }

    public function internalTags(): MorphToMany
    {
        return $this->tagsWithScope('program_internal');
    }

    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'exercise_program_exercises')
            ->using(ExerciseProgramExercise::class)
            ->withPivot(['id', 'sort'])
            ->orderByPivot('sort')
            ->withTimestamps();
    }

    public function duplicate(): self
    {
        $clone = $this->replicate(['id']);
        $clone->save();

        foreach ($this->exercises()->withPivot('sort')->get() as $exercise) {
            ExerciseProgramExercise::create([
                'exercise_program_id' => $clone->id,
                'exercise_id' => $exercise->id,
                'sort' => $exercise->pivot->sort ?? 0,
            ]);
        }

        return $clone;
    }
}
