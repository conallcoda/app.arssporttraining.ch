<?php

namespace Coda\SchemaKit\Runtime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StoredFieldValue extends Model
{
    protected $table = 'schema_field_values';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'field_revision_id',
        'facet_revision_id',
        'schema_key',
        'facet_key',
        'field_key',
        'scope_type',
        'scope_id',
        'taxonomy_type',
        'taxonomy_term_id',
        'value_kind',
        'value_string',
        'value_text',
        'value_number',
        'value_boolean',
        'value_date',
        'value_json',
        'canonical_key',
        'ordinal_value',
        'position',
        'source',
        'provenance_json',
    ];

    protected function casts(): array
    {
        return [
            'value_boolean' => 'bool',
            'value_date' => 'date',
            'value_json' => 'array',
            'ordinal_value' => 'int',
            'position' => 'int',
            'provenance_json' => 'array',
        ];
    }

    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }

    public function fieldRevision(): BelongsTo
    {
        return $this->belongsTo(StoredFieldRevision::class, 'field_revision_id');
    }

    public function facetRevision(): BelongsTo
    {
        return $this->belongsTo(StoredFacetRevision::class, 'facet_revision_id');
    }
}
