<?php

namespace App\Models\Exercise;

use App\Data\Exercise\ExerciseConfig;
use App\Models\Tag;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Coda\Cms\Models\Concerns\HasTags;
use Coda\Cms\Models\Contracts\Taggable;
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
        'external_id',
        'template_id',
    ];

    protected $attributes = [
        'config' => '{"settings":[],"overrides":{"cells":[],"weeks":[]}}',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(ExerciseTemplate::class);
    }

    public function external(): BelongsTo
    {
        return $this->belongsTo(ExerciseExternal::class, 'external_id');
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
