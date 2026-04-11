<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProgramSlotSet extends Model
{
    use HasFactory;

    protected $table = 'training_program_slot_sets';

    protected $fillable = [
        'training_program_slot_exercise_id',
        'set_number',
        'status',
        'has_any_modification',
        'athlete_note',
        'completed_at',
        'skipped_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrainingProgramSlotSetStatusEnum::class,
            'has_any_modification' => 'bool',
            'completed_at' => 'datetime',
            'skipped_at' => 'datetime',
        ];
    }

    public function slotExercise(): BelongsTo
    {
        return $this->belongsTo(TrainingProgramSlotExercise::class, 'training_program_slot_exercise_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(TrainingProgramSlotSetValue::class, 'training_program_slot_set_id');
    }
}
