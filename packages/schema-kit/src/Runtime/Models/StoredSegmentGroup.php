<?php

namespace Coda\SchemaKit\Runtime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoredSegmentGroup extends Model
{
    protected $table = 'schema_segment_groups';

    protected $fillable = [
        'schema_definition_id',
        'slug',
        'label',
        'description',
        'scope_type',
        'scope_id',
        'meta',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_system' => 'bool',
            'is_active' => 'bool',
        ];
    }

    public function schema(): BelongsTo
    {
        return $this->belongsTo(StoredSchema::class, 'schema_definition_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(StoredSegment::class, 'segment_group_id');
    }
}
