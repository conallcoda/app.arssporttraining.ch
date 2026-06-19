<?php

namespace Coda\SchemaKit\Runtime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoredFacet extends Model
{
    protected $table = 'schema_facets';

    protected $fillable = [
        'schema_definition_id',
        'key',
        'facet_group_key',
        'label',
        'description',
        'data_class',
        'data_path',
        'infer_fields',
        'storage_mode',
        'storage_config',
        'meta',
        'is_dynamic',
        'is_active',
        'current_revision_id',
    ];

    protected function casts(): array
    {
        return [
            'infer_fields' => 'bool',
            'storage_config' => 'array',
            'meta' => 'array',
            'is_dynamic' => 'bool',
            'is_active' => 'bool',
        ];
    }

    public function schema(): BelongsTo
    {
        return $this->belongsTo(StoredSchema::class, 'schema_definition_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(StoredFacetRevision::class, 'facet_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(StoredField::class, 'facet_id');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(StoredFacetRevision::class, 'current_revision_id');
    }
}
