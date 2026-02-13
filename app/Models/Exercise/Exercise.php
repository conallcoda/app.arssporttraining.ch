<?php

namespace App\Models\Exercise;

use App\Cms\Models\Concerns\HasQueryBuilder;
use App\Cms\Models\Concerns\HasTags;
use App\Cms\Models\Contracts\Taggable;
use App\Data\Exercise\ExerciseConfig;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exercise extends Model implements Taggable
{
    use HasQueryBuilder;
    use HasTags;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'video_url',
        'instructions',
        'config',
        'category_id',
    ];

    protected function casts(): array
    {
        return [
            'config' => ExerciseConfig::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'category_id');
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
