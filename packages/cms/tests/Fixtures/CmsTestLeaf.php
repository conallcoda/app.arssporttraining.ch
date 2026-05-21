<?php

namespace Coda\Cms\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsTestLeaf extends Model
{
    protected $table = 'cms_test_leaves';

    protected $fillable = [
        'group_id',
        'name',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CmsTestItem::class, 'group_id');
    }
}
