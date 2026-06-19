<?php

namespace App\Models\Training;

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExerciseSettingSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_id',
        'exercise_program_exercise_id',
        'training_program_id',
        'user_id',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function programExercise(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgramExercise::class, 'exercise_program_exercise_id');
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function slotExercises(): HasMany
    {
        return $this->hasMany(TrainingProgramSlotExercise::class, 'exercise_setting_snapshot_id');
    }
}
