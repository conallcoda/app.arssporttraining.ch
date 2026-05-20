<?php

namespace Coda\Cms\Tests\Fixtures;

use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsTestItem extends Model
{
    use HasFactory;
    use HasQueryBuilder;
    use SoftDeletes;

    protected $table = 'cms_test_items';

    protected $fillable = [
        'name',
        'status',
        'priority',
        'parent_id',
        'sort_order',
        'is_active',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'published_at' => 'date',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function scopeRoots($query): void
    {
        $query->whereNull('parent_id');
    }

    protected static function newFactory(): CmsTestItemFactory
    {
        return CmsTestItemFactory::new();
    }
}
