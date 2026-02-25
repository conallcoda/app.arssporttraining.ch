<?php

namespace App\Models;

use App\Data\Training\Config\PlanTemplateConfig;
use App\Models\Contracts\Plannable;
use Coda\Cms\Models\Concerns\SyncsSortableRelations;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanTemplate extends Model implements Plannable
{
    use SoftDeletes;
    use SyncsSortableRelations;

    protected $fillable = [
        'name',
        'config',
    ];

    protected function config(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value
                ? PlanTemplateConfig::from(json_decode($value, true))
                : PlanTemplateConfig::initialize(),
            set: fn (PlanTemplateConfig|array $value) => json_encode(
                $value instanceof PlanTemplateConfig ? $value->toArray() : $value
            ),
        );
    }

    public function programs(): MorphMany
    {
        return $this->morphMany(TrainingPlanProgram::class, 'plannable');
    }

    public function isTemplate(): bool
    {
        return true;
    }
}
