<?php

namespace App\Models\Exercise;

use App\Casts\ExercisePlanConfigCast;
use App\Models\Concerns\HasPlanConfigOverrides;
use Coda\Cms\Models\Concerns\HasOwner;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Coda\Cms\Models\Concerns\SyncsSortableRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExercisePlan extends Model
{
    use HasOwner;
    use HasPlanConfigOverrides;
    use HasQueryBuilder;
    use SoftDeletes;
    use SyncsSortableRelations;

    protected $table = 'exercise_plans';

    protected $fillable = [
        'name',
        'config',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'config' => ExercisePlanConfigCast::class,
        ];
    }

    public function ownedPrograms(): MorphMany
    {
        return $this->morphMany(ExerciseProgram::class, 'parent');
    }

    public function isTemplate(): bool
    {
        return true;
    }
}
