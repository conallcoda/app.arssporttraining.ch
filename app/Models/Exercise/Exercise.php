<?php

namespace App\Models\Exercise;

use App\Cms\Models\Concerns\HasQueryBuilder;
use App\Cms\Models\Concerns\HasTags;
use App\Cms\Models\Contracts\Taggable;
use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\ExerciseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exercise extends Model implements Taggable
{
    use HasQueryBuilder;
    use HasTags;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExerciseType::class,
            'config' => ExerciseConfig::class,
        ];
    }

    public function categories(): MorphToMany
    {
        return $this->tagsWithScope('exercise_category');
    }

    public function equipment(): MorphToMany
    {
        return $this->tagsWithScope('exercise_equipment');
    }

    public function modifiers(): MorphToMany
    {
        return $this->tagsWithScope('exercise_modifiers');
    }
}
