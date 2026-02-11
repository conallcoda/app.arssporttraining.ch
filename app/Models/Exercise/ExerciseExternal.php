<?php

namespace App\Models\Exercise;

use App\Cms\Models\Concerns\HasTags;
use App\Cms\Models\Contracts\Taggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExerciseExternal extends Model implements Taggable
{
    use HasFactory;
    use HasTags;
    use SoftDeletes;

    protected $table = 'exercises_external';

    protected $fillable = [
        'source',
        'name',
        'short_name',
        'template',
        'video_url',
    ];

    protected function casts(): array
    {
        return [
            'template' => ExerciseExternalTemplate::class,
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
