<?php

namespace App\Models;

use App\Models\Exercise\Exercise;
use Coda\Cms\Models\Concerns\HasConfigData;
use Coda\Cms\Models\Concerns\SyncsSortableRelations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExercisePlanProgram extends Model
{
    use HasConfigData;
    use HasFactory;
    use SoftDeletes;
    use SyncsSortableRelations;

    protected $table = 'exercise_plan_programs';

    protected $fillable = [
        'plannable_type',
        'plannable_id',
        'program_category_id',
        'name',
        'sort',
    ];

    public function plannable(): MorphTo
    {
        return $this->morphTo();
    }

    public function programCategory(): BelongsTo
    {
        return $this->belongsTo(ProgramCategory::class);
    }

    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'exercise_plan_program_exercises')
            ->using(ExercisePlanProgramExercise::class)
            ->withPivot(['id', 'sort'])
            ->orderByPivot('sort')
            ->withTimestamps();
    }
}
