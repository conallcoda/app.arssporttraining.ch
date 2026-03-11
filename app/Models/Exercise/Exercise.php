<?php

namespace App\Models\Exercise;

use App\Data\Exercise\ExerciseConfig;
use App\Models\Concerns\HasOwner;
use App\Models\Tag;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Coda\Cms\Models\Concerns\HasTags;
use Coda\Cms\Models\Contracts\HasCollectionPaths;
use Coda\Cms\Models\Contracts\Taggable;
use Database\Factories\ExerciseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Exercise extends Model implements HasCollectionPaths, HasMedia, Taggable
{
    use HasFactory;
    use HasOwner;
    use HasQueryBuilder;
    use HasTags;
    use InteractsWithMedia;
    use SoftDeletes;

    protected static function newFactory(): ExerciseFactory
    {
        return ExerciseFactory::new();
    }

    protected $fillable = [
        'name',
        'video_url',
        'instructions',
        'config',
        'category_id',
        'external_id',
        'template_id',
        'owner_id',
    ];

    protected $attributes = [
        'config' => '{"settings":[],"overrides":{"cells":[],"weeks":[]}}',
    ];

    public function getCollectionBasePath(string $collectionName): ?string
    {
        return match ($collectionName) {
            'photos' => 'exercises/'.$this->getKey(),
            default => null,
        };
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

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

    public function internalTags(): MorphToMany
    {
        return $this->tagsWithScope('exercise_internal');
    }
}
