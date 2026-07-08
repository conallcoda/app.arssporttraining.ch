<?php

namespace Coda\SchemaKit\Runtime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StoredFieldFact extends Model
{
    protected $table = 'schema_field_facts';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'schema_key',
        'facet_key',
        'field_key',
        'field_revision_id',
        'facet_revision_id',
        'conference_edition_id',
        'scope_type',
        'scope_id',
        'taxonomy_type',
        'taxonomy_term_id',
        'value_kind',
        'canonical_key',
        'ordinal_value',
        'value_string',
        'value_number',
        'value_boolean',
        'value_date',
        'value_text',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'conference_edition_id' => 'int',
            'ordinal_value' => 'int',
            'value_number' => 'float',
            'value_boolean' => 'bool',
            'value_date' => 'date',
            'position' => 'int',
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
