<?php

namespace App\Models\Exercise;

use App\Data\Training\Config\ExercisePlanConfig;
use Coda\Cms\Models\Concerns\HasOwner;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Coda\Cms\Models\Concerns\SyncsSortableRelations;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExercisePlan extends Model
{
    use HasOwner;
    use HasQueryBuilder;
    use SoftDeletes;
    use SyncsSortableRelations;

    protected $table = 'exercise_plans';

    protected $fillable = [
        'name',
        'config',
        'owner_id',
    ];

    protected function config(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value
                ? ExercisePlanConfig::from(json_decode($value, true))
                : ExercisePlanConfig::initialize(),
            set: fn (ExercisePlanConfig|array $value) => json_encode(
                $value instanceof ExercisePlanConfig ? $value->toArray() : $value
            ),
        );
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
