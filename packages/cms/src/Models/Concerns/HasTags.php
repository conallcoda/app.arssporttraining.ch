<?php

namespace Coda\Cms\Models\Concerns;

use Coda\Cms\Models\TaggablePivot;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Schema;

trait HasTags
{
    /** @var array<string, array<int, string>> */
    protected static array $taggablePivotColumnsCache = [];

    public function tags(): MorphToMany
    {
        $tagModel = config('cms.models.tag');

        return $this->morphToMany($tagModel, 'taggable')
            ->using(TaggablePivot::class)
            ->withPivot($this->taggablePivotColumns())
            ->orderByPivot('sort')
            ->withTimestamps();
    }

    public function tagsWithScope(string $scope): MorphToMany
    {
        return $this->tags()->where('scope', $scope);
    }

    /**
     * @return array<int, string>
     */
    protected function taggablePivotColumns(): array
    {
        $cacheKey = static::class;

        if (isset(static::$taggablePivotColumnsCache[$cacheKey])) {
            return static::$taggablePivotColumnsCache[$cacheKey];
        }

        $columns = ['sort'];

        if (Schema::hasColumn('taggables', 'score')) {
            $columns[] = 'score';
        }

        if (Schema::hasColumn('taggables', 'extra')) {
            $columns[] = 'extra';
        }

        static::$taggablePivotColumnsCache[$cacheKey] = $columns;

        return $columns;
    }
}
