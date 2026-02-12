<?php

namespace App\Models;

use App\Cms\Models\Concerns\HasQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class Tag extends Model
{
    use HasFactory;
    use HasQueryBuilder;
    use HasRecursiveRelationships;
    use HasSlug;
    use SoftDeletes;

    protected $fillable = [
        'scope',
        'parent_id',
        'sort_order',
        'name',
        'short_name',
        'slug',
    ];

    protected function casts(): array
    {
        return [];
    }

    protected static function booted(): void
    {
        static::saving(function (Tag $tag) {
            if ($tag->parent_id !== null) {
                $parent = Tag::find($tag->parent_id);

                if ($parent) {
                    $tag->scope = $parent->scope;
                }
            }
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->extraScope(fn (Builder $builder) => $builder->where('scope', $this->scope));
    }

    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope', $scope);
    }
}
