<?php

namespace App\Models\Training;

use App\Models\Users\User;
use Coda\Cms\Models\Concerns\HasOwner;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Database\Factories\TrainingProgramSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProgramSlot extends Model
{
    /** @use HasFactory<TrainingProgramSlotFactory> */
    use HasFactory;

    use HasOwner;
    use HasQueryBuilder;

    protected static function newFactory(): TrainingProgramSlotFactory
    {
        return TrainingProgramSlotFactory::new();
    }

    protected $table = 'training_program_slots';

    protected $fillable = [
        'training_program_id',
        'user_id',
        'datetime',
        'scheduled_date',
        'status',
        'compiled_at',
        'compiled_version',
        'exercise_count',
        'completed_exercise_count',
        'partial_exercise_count',
        'skipped_exercise_count',
        'pending_exercise_count',
        'has_any_modification',
        'completed_at',
        'cancelled_at',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'datetime' => 'datetime',
            'scheduled_date' => 'date',
            'status' => TrainingProgramSlotStatusEnum::class,
            'compiled_at' => 'datetime',
            'has_any_modification' => 'bool',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(TrainingProgramSlotExercise::class);
    }

    protected static function booted(): void
    {
        static::observe(\App\Observers\TrainingProgramSlotObserver::class);
    }
}
