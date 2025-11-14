<?php

namespace App\Models\Metrics;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\SchemalessAttributes\SchemalessAttributes;
use Illuminate\Database\Eloquent\Builder;

class Metric extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'metric_type_id',
        'metricable_type',
        'metricable_id',
        'value',
    ];

    public function initializeHasExtraData()
    {
        $this->casts['value'] = SchemalessAttributes::class;
    }

    public function scopeWithExtraAttributes(): Builder
    {
        return $this->extra->modelScope();
    }

    public function metricable(): MorphTo
    {
        return $this->morphTo();
    }

    public function metricType(): BelongsTo
    {
        return $this->belongsTo(MetricType::class);
    }

    public static function getExtraConfig(?Model $model = null): array
    {
        return [];
    }

    public static function genericsAnd(array $additionalTypes): array
    {
        $generics = [
            'boolean',
            'duration',
            'number',
            'percentage'
        ];
        $extended = array_unique(array_merge($generics, $additionalTypes));
        sort($extended);
        return $extended;
    }
}
