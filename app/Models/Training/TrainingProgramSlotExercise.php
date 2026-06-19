<?php

namespace App\Models\Training;

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgramExercise;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProgramSlotExercise extends Model
{
    use HasFactory;

    protected $table = 'training_program_slot_exercises';

    protected $fillable = [
        'training_program_slot_id',
        'exercise_id',
        'exercise_program_exercise_id',
        'sort',
        'group',
        'type',
        'status',
        'set_count',
        'completed_set_count',
        'modified_set_count',
        'skipped_set_count',
        'pending_set_count',
        'has_any_modification',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => 'string',
            'status' => TrainingProgramSlotExerciseStatusEnum::class,
            'has_any_modification' => 'bool',
            'completed_at' => 'datetime',
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(TrainingProgramSlot::class, 'training_program_slot_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function programExercise(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgramExercise::class, 'exercise_program_exercise_id');
    }

    public function sets(): HasMany
    {
        return $this->hasMany(TrainingProgramSlotSet::class, 'training_program_slot_exercise_id');
    }
}
