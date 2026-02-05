<?php

namespace App\Models;

use App\Cms\Models\Concerns\HasConfigData;
use App\Cms\Models\Concerns\SyncsSortableRelations;
use App\Models\Exercise\Exercise;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingPlanProgram extends Model
{
    use HasConfigData;
    use HasFactory;
    use SoftDeletes;
    use SyncsSortableRelations;

    protected $fillable = [
        'training_plan_id',
        'name',
        'sort',
    ];

    public function trainingPlan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class);
    }

    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'training_plan_program_exercises')
            ->using(TrainingPlanProgramExercise::class)
            ->withPivot(['sort', 'config'])
            ->orderByPivot('sort')
            ->withTimestamps();
    }
}
