<?php

namespace Coda\SchemaKit\Runtime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoredSegment extends Model
{
    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_USER = 'user';

    protected $table = 'schema_segments';

    protected $fillable = [
        'schema_definition_id',
        'segment_group_id',
        'slug',
        'label',
        'description',
        'predicate',
        'scope_type',
        'scope_id',
        'definition_source',
        'is_editable',
        'is_deletable',
        'meta',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_editable' => 'bool',
            'is_deletable' => 'bool',
            'meta' => 'array',
            'is_active' => 'bool',
        ];
    }

    public function schema(): BelongsTo
    {
        return $this->belongsTo(StoredSchema::class, 'schema_definition_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StoredSegmentGroup::class, 'segment_group_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(StoredSegmentMember::class, 'schema_segment_id');
    }
}
