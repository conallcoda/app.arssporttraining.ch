<?php

namespace Coda\SchemaKit\Runtime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoredSchema extends Model
{
    protected $table = 'schema_definitions';

    protected $fillable = [
        'key',
        'label',
        'plural_label',
        'model_class',
        'meta',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_active' => 'bool',
        ];
    }

    public function facets(): HasMany
    {
        return $this->hasMany(StoredFacet::class, 'schema_definition_id');
    }

    public function segmentGroups(): HasMany
    {
        return $this->hasMany(StoredSegmentGroup::class, 'schema_definition_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(StoredSegment::class, 'schema_definition_id');
    }
}
