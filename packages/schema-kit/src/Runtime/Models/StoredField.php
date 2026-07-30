<?php

namespace Coda\SchemaKit\Runtime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoredField extends Model
{
    protected $table = 'schema_fields';

    protected $fillable = [
        'facet_id',
        'key',
        'label',
        'definition_type',
        'query_strategy',
        'is_repeatable',
        'is_active',
        'current_revision_id',
    ];

    protected function casts(): array
    {
        return [
            'is_repeatable' => 'bool',
            'is_active' => 'bool',
        ];
    }

    public function facet(): BelongsTo
    {
        return $this->belongsTo(StoredFacet::class, 'facet_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(StoredFieldRevision::class, 'field_id');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(StoredFieldRevision::class, 'current_revision_id');
    }
}
