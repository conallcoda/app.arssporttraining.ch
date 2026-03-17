<?php

namespace App\Models\Athlete;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricValue extends Model
{
    protected $table = 'user_metric_values';

    protected $fillable = [
        'submission_id',
        'field',
        'value',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(MetricSubmission::class, 'submission_id');
    }
}
