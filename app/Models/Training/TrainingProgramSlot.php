<?php

namespace App\Models\Training;

use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Database\Factories\TrainingProgramSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingProgramSlot extends Model
{
    /** @use HasFactory<TrainingProgramSlotFactory> */
    use HasFactory;

    use HasQueryBuilder;
    use SoftDeletes;

    protected static function newFactory(): TrainingProgramSlotFactory
    {
        return TrainingProgramSlotFactory::new();
    }

    protected $table = 'training_program_slots';

    protected $fillable = [
        'training_program_id',
        'date',
        'slot',
        'sort',
        'active',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'active' => 'boolean',
            'config' => 'array',
        ];
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }
}
