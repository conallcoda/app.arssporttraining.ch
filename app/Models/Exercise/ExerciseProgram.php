<?php

namespace App\Models\Exercise;

use App\Casts\ExercisePlanConfigCast;
use App\Models\Concerns\HasPlanConfigOverrides;
use App\Models\Tag;
use App\Observers\ExerciseProgramObserver;
use Coda\Cms\Models\Concerns\HasOwner;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Coda\Cms\Models\Concerns\HasTags;
use Coda\Cms\Models\Contracts\Taggable;
use Database\Factories\ExerciseProgramFactory;
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
    use HasPlanConfigOverrides;
    use HasQueryBuilder;
    use HasTags;
    use SoftDeletes;

    protected static function newFactory(): ExerciseProgramFactory
    {
        return ExerciseProgramFactory::new();
    }

    protected $fillable = [
        'name',
        'type',
        'exercise_category_id',
        'warm_up_program_id',
        'warm_down_program_id',
        'sort',
        'config',
        'parent_type',
        'parent_id',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExerciseProgramTypeEnum::class,
            'config' => ExercisePlanConfigCast::class,
        ];
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function warmUpProgram(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgram::class, 'warm_up_program_id');
    }

    public function warmDownProgram(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgram::class, 'warm_down_program_id');
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
            ->withPivot(['id', 'sort', 'group', 'type'])
            ->orderByPivot('sort')
            ->withTimestamps();
    }

    public function duplicate(): self
    {
        $clone = $this->replicate(['id']);

        $config = $clone->config;
        foreach (array_keys($config->exercises) as $programExerciseId) {
            $overrides = $config->defaultExerciseOverrides((int) $programExerciseId);
            if ($overrides->hasAnyGridOverrides()) {
                $overrides->baselineGridOverrides = $overrides->gridOverrides;
                $config->setDefaultExerciseOverrides((int) $programExerciseId, $overrides);
            }
        }

        $clone->save();

        $pivotIdMap = [];

        foreach ($this->exercises()->withPivot(['id', 'sort', 'group', 'type'])->get() as $exercise) {
            $newPivot = ExerciseProgramExercise::create([
                'exercise_program_id' => $clone->id,
                'exercise_id' => $exercise->id,
                'sort' => $exercise->pivot->sort ?? 0,
                'group' => $exercise->pivot->group,
                'type' => $exercise->pivot->type ?? 'main',
            ]);

            $pivotIdMap[(int) $exercise->pivot->id] = $newPivot->id;
        }

        $cloneConfig = $clone->config;
        $cloneConfig->remapDefaultExerciseOverrides($pivotIdMap);
        $cloneConfig->remapUserExerciseOverrides($pivotIdMap);
        $clone->config = $cloneConfig;
        $clone->save();

        return $clone;
    }
    protected static function booted(): void
    {
        static::observe(ExerciseProgramObserver::class);
    }
}
