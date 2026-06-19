<?php

namespace App\Training;

use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Settings\AbstractSetting;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSetValue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TrainingSessionPlannedValueService
{
    public function __construct(
        private readonly TrainingSessionStatusService $statusService,
    ) {}

    public function saveExercisePlannedValues(TrainingProgramSlotExercise $exercise, array $submittedValues, bool $onlyProvided = false): bool
    {
        return DB::transaction(function () use ($exercise, $submittedValues, $onlyProvided): bool {
            $exercise = TrainingProgramSlotExercise::query()
                ->with(['slot', 'exercise', 'sets.values'])
                ->lockForUpdate()
                ->findOrFail($exercise->id);

            $hasChanges = false;

            foreach ($exercise->sets as $set) {
                $existingSettingKeys = $set->values->pluck('setting_key')->all();

                foreach ($set->values as $valueRow) {
                    $path = $set->id.'.'.$valueRow->setting_key;

                    if ($onlyProvided && ! Arr::has($submittedValues, $path)) {
                        continue;
                    }

                    $attributes = $this->buildPlannedSnapshotAttributes(
                        $exercise,
                        $valueRow,
                        data_get($submittedValues, $path),
                    );

                    if (! $this->rowNeedsUpdate($valueRow, $attributes)) {
                        continue;
                    }

                    $valueRow->forceFill($attributes)->save();
                    $hasChanges = true;
                }

                if (! isset($submittedValues[$set->id]) || ! is_array($submittedValues[$set->id])) {
                    continue;
                }

                foreach ($submittedValues[$set->id] as $settingKey => $submittedValue) {
                    if (in_array($settingKey, $existingSettingKeys, true)) {
                        continue;
                    }

                    if ($onlyProvided && ! Arr::has($submittedValues, $set->id.'.'.$settingKey)) {
                        continue;
                    }

                    $attributes = $this->buildPlannedSnapshotAttributesForSetting(
                        $exercise,
                        $settingKey,
                        $submittedValue,
                    );

                    $set->values()->create([
                        'setting_key' => $settingKey,
                        ...$attributes,
                    ]);

                    $hasChanges = true;
                }
            }

            $this->statusService->refreshExerciseState($exercise);

            return $hasChanges;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPlannedSnapshotAttributes(
        TrainingProgramSlotExercise $exercise,
        TrainingProgramSlotSetValue $valueRow,
        mixed $submittedValue,
    ): array {
        return $this->buildPlannedSnapshotAttributesForSetting(
            $exercise,
            $valueRow->setting_key,
            $submittedValue,
            $valueRow->unit,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPlannedSnapshotAttributesForSetting(
        TrainingProgramSlotExercise $exercise,
        string $settingKey,
        mixed $submittedValue,
        ?string $fallbackUnit = null,
    ): array {
        $settingClass = ExerciseSetting::tryFrom($settingKey)?->settingClass();
        $config = $this->resolveSettingConfig($exercise, $settingKey);

        if (! is_string($settingClass) || ! is_subclass_of($settingClass, AbstractSetting::class)) {
            return $this->encodeFallbackPlannedValue($submittedValue, $fallbackUnit);
        }

        $normalized = $settingClass::normalizeAthleteValue($submittedValue, $config);

        if ($normalized === null) {
            return [
                'planned_value_type' => null,
                'planned_int_value' => null,
                'planned_decimal_value' => null,
                'planned_string_value' => null,
                'planned_json_value' => null,
                'unit' => $settingClass::resolveUnitLabel($config),
            ];
        }

        $valueType = $settingClass::athleteValueType($normalized, $config);
        $canonicalValue = $settingClass::athleteCanonicalValue($normalized, $config);

        return match ($valueType) {
            'int' => [
                'planned_value_type' => 'int',
                'planned_int_value' => (int) $normalized,
                'planned_decimal_value' => null,
                'planned_string_value' => null,
                'planned_json_value' => $canonicalValue,
                'unit' => $settingClass::resolveUnitLabel($config),
            ],
            'decimal' => [
                'planned_value_type' => 'decimal',
                'planned_int_value' => null,
                'planned_decimal_value' => round((float) $normalized, 3),
                'planned_string_value' => null,
                'planned_json_value' => $canonicalValue,
                'unit' => $settingClass::resolveUnitLabel($config),
            ],
            'json' => [
                'planned_value_type' => 'json',
                'planned_int_value' => null,
                'planned_decimal_value' => null,
                'planned_string_value' => null,
                'planned_json_value' => is_array($normalized) ? $normalized : $canonicalValue,
                'unit' => $settingClass::resolveUnitLabel($config),
            ],
            default => [
                'planned_value_type' => 'string',
                'planned_int_value' => null,
                'planned_decimal_value' => null,
                'planned_string_value' => (string) $normalized,
                'planned_json_value' => $canonicalValue,
                'unit' => $settingClass::resolveUnitLabel($config),
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSettingConfig(TrainingProgramSlotExercise $exercise, string $settingKey): array
    {
        $config = $exercise->exercise?->config;
        $setting = $config?->{$settingKey};
        $sets = $config?->sets;
        $settingConfig = is_object($setting) && method_exists($setting, 'toArray')
            ? $setting->toArray()
            : [];

        $settingConfig['_sets'] = is_object($sets) && method_exists($sets, 'toArray')
            ? $sets->toArray()
            : [];

        return $settingConfig;
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeFallbackPlannedValue(mixed $value, ?string $unit): array
    {
        if ($value === null || $value === '') {
            return [
                'planned_value_type' => null,
                'planned_int_value' => null,
                'planned_decimal_value' => null,
                'planned_string_value' => null,
                'planned_json_value' => null,
                'unit' => $unit,
            ];
        }

        if (is_array($value)) {
            return [
                'planned_value_type' => 'json',
                'planned_int_value' => null,
                'planned_decimal_value' => null,
                'planned_string_value' => null,
                'planned_json_value' => $value,
                'unit' => $unit,
            ];
        }

        if (is_float($value)) {
            return [
                'planned_value_type' => 'decimal',
                'planned_int_value' => null,
                'planned_decimal_value' => round($value, 3),
                'planned_string_value' => null,
                'planned_json_value' => null,
                'unit' => $unit,
            ];
        }

        if (is_int($value)) {
            return [
                'planned_value_type' => 'int',
                'planned_int_value' => $value,
                'planned_decimal_value' => null,
                'planned_string_value' => null,
                'planned_json_value' => null,
                'unit' => $unit,
            ];
        }

        if (is_string($value) && is_numeric($value) && str_contains($value, '.')) {
            return [
                'planned_value_type' => 'decimal',
                'planned_int_value' => null,
                'planned_decimal_value' => round((float) $value, 3),
                'planned_string_value' => null,
                'planned_json_value' => null,
                'unit' => $unit,
            ];
        }

        if (is_numeric($value)) {
            return [
                'planned_value_type' => 'int',
                'planned_int_value' => (int) $value,
                'planned_decimal_value' => null,
                'planned_string_value' => null,
                'planned_json_value' => null,
                'unit' => $unit,
            ];
        }

        return [
            'planned_value_type' => 'string',
            'planned_int_value' => null,
            'planned_decimal_value' => null,
            'planned_string_value' => trim((string) $value),
            'planned_json_value' => null,
            'unit' => $unit,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rowNeedsUpdate(TrainingProgramSlotSetValue $valueRow, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            if ($valueRow->{$key} !== $value) {
                return true;
            }
        }

        return false;
    }
}
