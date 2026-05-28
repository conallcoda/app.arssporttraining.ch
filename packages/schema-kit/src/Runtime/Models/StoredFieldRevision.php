<?php

namespace Coda\SchemaKit\Runtime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoredFieldRevision extends Model
{
    protected $table = 'schema_field_revisions';

    protected $fillable = [
        'field_id',
        'facet_revision_id',
        'version',
        'content_hash',
        'field_type',
        'type_config',
        'storage_mode',
        'storage_config',
        'attribute_name',
        'required',
        'readable',
        'writable',
        'form_visible',
        'help',
        'meta',
        'published_at',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'type_config' => 'array',
            'storage_config' => 'array',
            'required' => 'bool',
            'readable' => 'bool',
            'writable' => 'bool',
            'form_visible' => 'bool',
            'meta' => 'array',
            'published_at' => 'datetime',
            'is_current' => 'bool',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(StoredField::class, 'field_id');
    }

    public function facetRevision(): BelongsTo
    {
        return $this->belongsTo(StoredFacetRevision::class, 'facet_revision_id');
    }
}
