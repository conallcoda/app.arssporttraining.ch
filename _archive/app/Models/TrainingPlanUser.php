<?php

namespace App\Models;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TrainingPlanUser extends Pivot
{
    protected $table = 'training_plan_user';

    public $incrementing = true;

    protected $fillable = [
        'training_plan_id',
        'user_id',
        'sort',
    ];

    public function trainingPlan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
