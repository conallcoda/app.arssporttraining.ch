<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

trait HasExtraData
{
    public function initializeHasExtraData()
    {
        $this->casts['extra'] = SchemalessAttributes::class;
    }

    public function scopeWithExtraAttributes(): Builder
    {
        return $this->extra->modelScope();
    }
}
