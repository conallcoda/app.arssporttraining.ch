<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingProgramSlotSetValue extends Model
{
    use HasFactory;

    protected $table = 'training_program_slot_set_values';

    protected $fillable = [
        'training_program_slot_set_id',
        'setting_key',
        'planned_value_type',
        'planned_int_value',
        'planned_decimal_value',
        'planned_string_value',
        'planned_json_value',
        'actual_value_type',
        'actual_int_value',
        'actual_decimal_value',
        'actual_string_value',
        'actual_json_value',
        'unit',
        'is_modified',
    ];

    protected function casts(): array
    {
        return [
            'planned_json_value' => 'array',
            'actual_json_value' => 'array',
            'is_modified' => 'bool',
        ];
    }

    public function slotSet(): BelongsTo
    {
        return $this->belongsTo(TrainingProgramSlotSet::class, 'training_program_slot_set_id');
    }
}
