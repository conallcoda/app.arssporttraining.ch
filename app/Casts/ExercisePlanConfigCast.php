<?php

namespace App\Casts;

use App\Data\Training\Config\ExercisePlanConfig;
use App\Models\Concerns\HasPlanConfigOverrides;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** @implements CastsAttributes<ExercisePlanConfig, ExercisePlanConfig|array> */
class ExercisePlanConfigCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ExercisePlanConfig
    {
        $configData = is_string($value) ? json_decode($value, true) : null;

        if (! is_array($configData)) {
            $configData = [];
        }

        if ($model->exists && in_array(HasPlanConfigOverrides::class, class_uses_recursive($model), true)) {
            $configData['overrideValues'] = $model->planConfigOverrides()
                ->get()
                ->map(fn ($override): array => $override->toFlatArray())
                ->all();
        } else {
            $configData['overrideValues'] = $configData['overrideValues'] ?? [];
        }

        if ($configData === []) {
            return ExercisePlanConfig::initialize();
        }

        return ExercisePlanConfig::from($configData);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (is_array($value)) {
            $value = ExercisePlanConfig::from($value);
        }

        if (! $value instanceof ExercisePlanConfig) {
            throw new InvalidArgumentException(
                'ExercisePlanConfigCast expects an ExercisePlanConfig instance or array.',
            );
        }

        if (in_array(HasPlanConfigOverrides::class, class_uses_recursive($model), true)) {
            $model->stashPendingPlanConfigOverrideRows($value->flatOverrideRows());
        }

        return [$key => json_encode($value->toPersistedArray())];
    }
}
