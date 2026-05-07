<?php

namespace App\Models\Training;

use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TrainingPlanValueRevision extends Model
{
    use HasFactory;

    protected $table = 'training_plan_value_revisions';

    protected $fillable = [
        'batch_id',
        'owner_type',
        'owner_id',
        'program_exercise_id',
        'user_id',
        'setting_key',
        'week_index',
        'session_index',
        'set_index',
        'changed_by',
        'source',
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
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TrainingRevisionBatch::class, 'batch_id');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programExercise(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgramExercise::class, 'program_exercise_id');
    }
}
