<?php

namespace App\Casts;

use App\Data\Training\Config\ExercisePlanConfig;
use App\Models\Concerns\HasPlanConfigOverrides;
use ArrayObject;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** @implements CastsAttributes<ExercisePlanConfig, ExercisePlanConfig|array> */
class ExercisePlanConfigCast implements CastsAttributes
{
    private const OVERRIDE_ROWS_CACHE_KEY = 'exercise-plan-config-override-rows-cache';

    public static function flushOverrideRowsCache(): void
    {
        if (app()->bound(self::OVERRIDE_ROWS_CACHE_KEY)) {
            app()->forgetInstance(self::OVERRIDE_ROWS_CACHE_KEY);
        }
    }

    public static function forgetOverrideRowsFor(Model $model): void
    {
        $cache = self::overrideRowsCache();

        unset($cache[self::overrideRowsCacheKey($model)]);
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ExercisePlanConfig
    {
        $configData = is_string($value) ? json_decode($value, true) : null;

        if (! is_array($configData)) {
            $configData = [];
        }

        if ($model->exists && in_array(HasPlanConfigOverrides::class, class_uses_recursive($model), true)) {
            $configData['overrideValues'] = $this->overrideRowsFor($model);
        } else {
            $configData['overrideValues'] = $configData['overrideValues'] ?? [];
        }

        if ($configData === []) {
            return ExercisePlanConfig::initialize();
        }

        $config = ExercisePlanConfig::from($configData);
        $config->sectionInstructions = is_array($configData['sectionInstructions'] ?? null)
            ? $configData['sectionInstructions']
            : [];

        return $config;
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overrideRowsFor(Model $model): array
    {
        $cacheKey = self::overrideRowsCacheKey($model);
        $cache = self::overrideRowsCache();

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        return $cache[$cacheKey] = DB::table('exercise_plan_config_overrides')
            ->select([
                'program_exercise_id',
                'user_id',
                'scope',
                'target',
                'week_index',
                'session_index',
                'set_index',
                'setting_key',
                'value',
            ])
            ->where('owner_type', $model->getMorphClass())
            ->where('owner_id', $model->getKey())
            ->get()
            ->map(fn ($row): array => [
                'programExerciseId' => (int) $row->program_exercise_id,
                'userId' => $row->user_id !== null ? (int) $row->user_id : null,
                'scope' => $row->scope,
                'target' => $row->target,
                'week' => (int) $row->week_index,
                'session' => (int) $row->session_index,
                'set' => $row->set_index !== null ? (int) $row->set_index : null,
                'settingKey' => $row->setting_key,
                'value' => $row->value === null ? null : json_decode($row->value, true),
            ])
            ->all();
    }

    private static function overrideRowsCacheKey(Model $model): string
    {
        return $model->getMorphClass().':'.$model->getKey();
    }

    /** @return ArrayObject<string, array<int, array<string, mixed>>> */
    private static function overrideRowsCache(): ArrayObject
    {
        if (! app()->bound(self::OVERRIDE_ROWS_CACHE_KEY)) {
            app()->scoped(self::OVERRIDE_ROWS_CACHE_KEY, fn (): ArrayObject => new ArrayObject());
        }

        return app(self::OVERRIDE_ROWS_CACHE_KEY);
    }
}
