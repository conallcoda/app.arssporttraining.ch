<?php

namespace App\Models\Training;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingActualValueRevision extends Model
{
    use HasFactory;

    protected $table = 'training_actual_value_revisions';

    protected $fillable = [
        'batch_id',
        'training_program_slot_set_value_id',
        'recorded_by',
        'source',
        'was_explicit',
        'is_explicit',
        'was_modified_from_plan',
        'is_modified_from_plan',
        'before_value_type',
        'before_int_value',
        'before_decimal_value',
        'before_string_value',
        'before_json_value',
        'after_value_type',
        'after_int_value',
        'after_decimal_value',
        'after_string_value',
        'after_json_value',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'before_json_value' => 'array',
            'after_json_value' => 'array',
            'was_explicit' => 'bool',
            'is_explicit' => 'bool',
            'was_modified_from_plan' => 'bool',
            'is_modified_from_plan' => 'bool',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TrainingRevisionBatch::class, 'batch_id');
    }

    public function valueRow(): BelongsTo
    {
        return $this->belongsTo(TrainingProgramSlotSetValue::class, 'training_program_slot_set_value_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
