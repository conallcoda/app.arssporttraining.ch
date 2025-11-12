<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    protected $fillable = [
        'training_week_id',
        'name',
        'day',
        'period',
    ];

    protected $casts = [
        'day' => 'integer',
        'period' => 'integer',
    ];

    public function week(): BelongsTo
    {
        return $this->belongsTo(TrainingWeek::class, 'training_week_id');
    }

    public function sets(): HasMany
    {
        return $this->hasMany(TrainingSet::class);
    }
}
