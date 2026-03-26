<?php

namespace App\Models\Training;

use App\Models\Users\User;
use Coda\Cms\Models\Concerns\HasOwner;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Database\Factories\TrainingProgramSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'datetime' => 'datetime',
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
}
