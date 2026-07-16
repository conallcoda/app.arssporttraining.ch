<?php

namespace Coda\SchemaKit\Runtime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoredFacetRevision extends Model
{
    protected $table = 'schema_facet_revisions';

    protected $fillable = [
        'facet_id',
        'version',
        'content_hash',
        'definition_json',
        'published_at',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'definition_json' => 'array',
            'published_at' => 'datetime',
            'is_current' => 'bool',
        ];
    }

    public function facet(): BelongsTo
    {
        return $this->belongsTo(StoredFacet::class, 'facet_id');
    }

    public function applicabilityRules(): HasMany
    {
        return $this->hasMany(StoredFacetApplicabilityRule::class, 'facet_revision_id');
    }
}
