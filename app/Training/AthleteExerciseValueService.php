<?php

namespace App\Training;

use App\Data\Exercise\ExerciseSetting;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSetValue;
use Illuminate\Support\Facades\DB;

class AthleteExerciseValueService
{
    public function __construct(
        private readonly TrainingSessionStatusService $statusService,
    ) {}

    public function saveExerciseValues(TrainingProgramSlotExercise $exercise, array $submittedValues): bool
    {
        return DB::transaction(function () use ($exercise, $submittedValues): bool {
            $exercise = TrainingProgramSlotExercise::query()
                ->with(['slot', 'exercise', 'sets.values'])
                ->lockForUpdate()
                ->findOrFail($exercise->id);

            $hasChanges = false;

            foreach ($exercise->sets as $set) {
                foreach ($set->values as $valueRow) {
                    $settingClass = ExerciseSetting::tryFrom($valueRow->setting_key)?->settingClass();

                    if ($settingClass === null) {
                        continue;
                    }

                    $config = $this->resolveSettingConfig($exercise, $valueRow->setting_key);
                    $normalized = $settingClass::normalizeAthleteValue(
                        data_get($submittedValues, $set->id.'.'.$valueRow->setting_key),
                        $config
                    );
                    $plannedValue = $this->extractPlannedValue($valueRow);
                    $isModified = ! $this->valuesEquivalent($normalized, $plannedValue);

                    $attributes = $isModified
                        ? $this->encodeActualValue($settingClass, $normalized, $config)
                        : $this->clearActualValue();

                    $attributes['is_modified'] = $isModified;

                    if ($this->rowNeedsUpdate($valueRow, $attributes)) {
                        $valueRow->forceFill($attributes)->save();
                        $hasChanges = true;
                    }
                }

                $this->statusService->recalculateSet($set->fresh('values'));
            }

            $this->statusService->recalculateExercise($exercise->fresh('slot', 'sets.values'));

            return $hasChanges;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSettingConfig(TrainingProgramSlotExercise $exercise, string $settingKey): array
    {
        $config = $exercise->exercise?->config;
        $setting = $config?->{$settingKey};

        return is_object($setting) && method_exists($setting, 'toArray')
            ? $setting->toArray()
            : [];
    }

    private function extractPlannedValue(TrainingProgramSlotSetValue $valueRow): mixed
    {
        return match ($valueRow->planned_value_type) {
            'int' => $valueRow->planned_int_value,
            'decimal' => $valueRow->planned_decimal_value !== null ? (float) $valueRow->planned_decimal_value : null,
            'json' => $valueRow->planned_json_value,
            default => $valueRow->planned_string_value,
        };
    }

    private function valuesEquivalent(mixed $left, mixed $right): bool
    {
        if (is_float($left) || is_float($right)) {
            return (float) $left === (float) $right;
        }

        return $left === $right;
    }

    /**
     * @param  class-string  $settingClass
     * @return array<string, mixed>
     */
    private function encodeActualValue(string $settingClass, mixed $value, array $config): array
    {
        $valueType = $settingClass::athleteValueType($value, $config);
        $canonicalValue = $settingClass::athleteCanonicalValue($value, $config);

        $row = [
            'actual_value_type' => $valueType,
            'actual_int_value' => null,
            'actual_decimal_value' => null,
            'actual_string_value' => null,
            'actual_json_value' => $canonicalValue,
        ];

        return match ($valueType) {
            'int' => array_merge($row, ['actual_int_value' => (int) $value]),
            'decimal' => array_merge($row, ['actual_decimal_value' => round((float) $value, 3)]),
            'json' => array_merge($row, ['actual_json_value' => $value]),
            default => array_merge($row, ['actual_string_value' => (string) $value]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function clearActualValue(): array
    {
        return [
            'actual_value_type' => null,
            'actual_int_value' => null,
            'actual_decimal_value' => null,
            'actual_string_value' => null,
            'actual_json_value' => null,
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
