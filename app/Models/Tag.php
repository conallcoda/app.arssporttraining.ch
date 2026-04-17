<?php

namespace App\Models;

use App\Models\Exercise\ExerciseTemplate;
use Coda\Cms\Models\Tag as CmsTag;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tag extends CmsTag
{
    protected $fillable = [
        'scope',
        'parent_id',
        'sort_order',
        'name',
        'short_name',
        'slug',
        'color',
        'owner_id',
        'default_exercise_template_id',
    ];

    public function defaultExerciseTemplate(): BelongsTo
    {
        return $this->belongsTo(ExerciseTemplate::class, 'default_exercise_template_id');
    }
}
