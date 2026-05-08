<?php

namespace Coda\Cms\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    public function tags(): MorphToMany
    {
        $tagModel = config('cms.models.tag');

        return $this->morphToMany($tagModel, 'taggable')
            ->withPivot(['sort'])
            ->orderByPivot('sort')
            ->withTimestamps();
    }

    public function tagsWithScope(string $scope): MorphToMany
    {
        return $this->tags()->where('scope', $scope);
    }
}
