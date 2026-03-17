<?php

namespace App\Models\Athlete;

use App\Data\Athlete\Metric\MetricEnum;
use App\Models\Users\User;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MetricSubmission extends Model
{
    use HasFactory;
    use HasQueryBuilder;
    use SoftDeletes;

    protected $table = 'user_metric_submissions';

    protected $fillable = [
        'user_id',
        'metric',
        'recorded_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'metric' => MetricEnum::class,
            'recorded_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function values(): HasMany
    {
        return $this->hasMany(MetricValue::class, 'submission_id');
    }

    public function scopeForAthlete(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function scopeForMetric(Builder $query, MetricEnum $metric): void
    {
        $query->where('metric', $metric);
    }

    public function getFieldValue(string $field): ?string
    {
        return $this->values->firstWhere('field', $field)?->value;
    }
}
