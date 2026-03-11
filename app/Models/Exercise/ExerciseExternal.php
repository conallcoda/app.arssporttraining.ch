<?php

namespace App\Models\Exercise;

use App\Models\Concerns\HasOwner;
use App\Models\Tag;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Coda\Cms\Models\Concerns\HasTags;
use Coda\Cms\Models\Contracts\Taggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExerciseExternal extends Model implements Taggable
{
    use HasFactory;
    use HasOwner;
    use HasQueryBuilder;
    use HasTags;
    use SoftDeletes;

    protected $table = 'exercises_external';

    protected $fillable = [
        'source',
        'name',
        'video_url',
        'category_id',
        'owner_id',
    ];

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
