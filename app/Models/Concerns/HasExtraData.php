<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    abstract public static function getExtraConfig(?Model $model = null): array;
}
