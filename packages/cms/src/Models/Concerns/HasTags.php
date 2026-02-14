<?php

namespace Coda\Cms\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')
            ->withPivot(['sort'])
            ->orderByPivot('sort')
            ->withTimestamps();
    }

    public function tagsWithScope(string $scope): MorphToMany
    {
        return $this->tags()->where('scope', $scope);
    }
}
