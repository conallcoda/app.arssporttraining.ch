<?php

namespace App\Models\Exercise;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExercisePlanConfigOverride extends Model
{
    protected $table = 'exercise_plan_config_overrides';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'program_exercise_id',
        'user_id',
        'scope',
        'target',
        'week_index',
        'session_index',
        'set_index',
        'setting_key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'week_index' => 'integer',
            'session_index' => 'integer',
            'set_index' => 'integer',
            'user_id' => 'integer',
            'program_exercise_id' => 'integer',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function programExercise(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgramExercise::class, 'program_exercise_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDecodedValue(): mixed
    {
        $raw = $this->attributes['value'] ?? null;

        return $raw === null ? null : json_decode($raw, true);
    }

    /** @return array<string, mixed> */
    public function toFlatArray(): array
    {
        return [
            'programExerciseId' => $this->program_exercise_id,
            'userId' => $this->user_id,
            'scope' => $this->scope,
            'target' => $this->target,
            'week' => $this->week_index,
            'session' => $this->session_index,
            'set' => $this->set_index,
            'settingKey' => $this->setting_key,
            'value' => $this->getDecodedValue(),
        ];
    }
}
