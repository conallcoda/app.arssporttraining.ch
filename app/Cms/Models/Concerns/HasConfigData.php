<?php

namespace App\Cms\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

trait HasConfigData
{
    public function initializeHasConfigData()
    {
        $this->casts['config'] = SchemalessAttributes::class;
    }

    public function scopeWithConfigAttributes(): Builder
    {
        return $this->config->modelScope();
    }
}
