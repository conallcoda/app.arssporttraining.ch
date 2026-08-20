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
        'session_index',
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
            'session_index' => 'integer',
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
        static::creating(function (self $slot): void {
            if ($slot->session_index !== null) {
                return;
            }

            $usedIndexes = static::query()
                ->where('training_program_id', $slot->training_program_id)
                ->where('user_id', $slot->user_id)
                ->whereNotNull('session_index')
                ->orderBy('session_index')
                ->pluck('session_index')
                ->map(fn (mixed $index): int => (int) $index)
                ->all();

            $nextIndex = 0;
            foreach ($usedIndexes as $usedIndex) {
                if ($usedIndex !== $nextIndex) {
                    break;
                }

                $nextIndex++;
            }

            $slot->session_index = $nextIndex;
        });

        static::observe(\App\Observers\TrainingProgramSlotObserver::class);
    }
}
