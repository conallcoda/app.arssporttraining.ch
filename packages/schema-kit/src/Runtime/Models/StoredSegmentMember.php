<?php

namespace Coda\SchemaKit\Runtime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StoredSegmentMember extends Model
{
    protected $table = 'schema_segment_members';

    protected $fillable = [
        'schema_segment_id',
        'entity_type',
        'entity_id',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(StoredSegment::class, 'schema_segment_id');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }
}
