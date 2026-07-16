<?php

namespace Coda\SchemaKit\Runtime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoredFacetApplicabilityRule extends Model
{
    protected $table = 'schema_facet_applicability_rules';

    protected $fillable = [
        'facet_revision_id',
        'schema_key',
        'scope_type',
        'scope_id',
        'taxonomy_type',
        'taxonomy_term_id',
        'segment_slug',
        'priority',
        'mode',
    ];

    public function facetRevision(): BelongsTo
    {
        return $this->belongsTo(StoredFacetRevision::class, 'facet_revision_id');
    }
}
