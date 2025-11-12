<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingBlock extends Model
{
    protected $fillable = [
        'training_season_id',
        'name',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(TrainingSeason::class, 'training_season_id');
    }

    public function weeks(): HasMany
    {
        return $this->hasMany(TrainingWeek::class);
    }
}
