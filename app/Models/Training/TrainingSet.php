<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSet extends Model
{
    protected $fillable = [
        'training_session_id',
        'sequence',
        'reps',
    ];

    protected $casts = [
        'reps' => 'integer',
        'sequence' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(TrainingExercise::class);
    }
}
