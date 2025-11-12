<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Exercise\Exercise;

class TrainingExercise extends Model
{
    protected $fillable = [
        'training_set_id',
        'exercise_id',
    ];

    public function set(): BelongsTo
    {
        return $this->belongsTo(TrainingSet::class, 'training_set_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
