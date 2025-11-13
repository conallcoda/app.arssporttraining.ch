<?php

namespace App\Models\Metrics;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasExtraData;
use Parental\HasChildren;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Metric extends Model
{
    use HasFactory, HasExtraData, HasChildren, SoftDeletes;

    protected $fillable = [
        'type',
        'metricable_type',
        'metricable_id',
        'value',
        'unit',
        'recorded_at',
        'notes',
        'extra',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'recorded_at' => 'date',
    ];

    /**
     * Get the parent metricable model (User, Exercise, TrainingExercise, etc.)
     */
    public function metricable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function getExtraConfig(?Model $model = null): array
    {
        return [];
    }
}
