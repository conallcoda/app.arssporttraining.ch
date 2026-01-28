<?php

namespace App\Models;

use App\Models\Users\UserGroup;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TrainingPlanUserGroup extends Pivot
{
    protected $table = 'training_plan_user_group';

    public $incrementing = true;

    protected $fillable = [
        'training_plan_id',
        'user_group_id',
        'sort',
    ];

    public function trainingPlan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class);
    }

    public function userGroup(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class);
    }
}
