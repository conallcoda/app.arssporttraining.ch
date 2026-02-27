<?php

namespace App\Models;

use App\Data\Training\Config\ExercisePlanConfig;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Coda\Cms\Models\Concerns\SyncsSortableRelations;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExercisePlan extends Model
{
    use HasQueryBuilder;
    use SoftDeletes;
    use SyncsSortableRelations;

    protected $table = 'exercise_plans';

    protected $fillable = [
        'name',
        'config',
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

    public function programIds(): array
    {
        $ids = [];

        foreach ($this->config->defaultScheduleWeeks() as $week) {
            $slots = $week['slots'] ?? [];
            if (isset($week['linkedTo']) && $week['linkedTo'] !== null) {
                continue;
            }
            foreach ($slots as $slot) {
                foreach ($slot['programs'] ?? [] as $programId) {
                    if (! in_array($programId, $ids)) {
                        $ids[] = $programId;
                    }
                }
            }
        }

        return $ids;
    }

    public function programs(): Collection
    {
        $ids = $this->programIds();

        if (empty($ids)) {
            return new Collection;
        }

        return ExerciseProgram::whereIn('id', $ids)
            ->with(['exercises' => fn ($q) => $q->orderByPivot('sort'), 'programCategory'])
            ->orderBy('name')
            ->get();
    }

    public function ownedPrograms(): MorphMany
    {
        return $this->morphMany(ExerciseProgram::class, 'owner');
    }

    public function isTemplate(): bool
    {
        return true;
    }
}
