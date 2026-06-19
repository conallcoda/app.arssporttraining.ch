<?php

namespace App\Support\Athlete\Concerns;

use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Settings\AbstractSetting;
use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Strategies\HeartRate\HeartRateZoneCellColors;
use Coda\Cms\Support\ColorPalette;
use Illuminate\Support\Collection;

trait FormatsProgramDetailsExerciseValues
{
    private const SETTING_PRIORITY = [
        'reps',
        'weight',
        'distance',
        'duration',
        'pace',
        'watts',
        'heartRate',
        'heartRateZone',
        'tempo',
        'rest',
        'note',
    ];

    protected function orderedSettings(array $settings): Collection
    {
        return collect($settings)
            ->unique()
            ->sortBy(function (string $setting): int {
                $priority = array_search($setting, self::SETTING_PRIORITY, true);

                return $priority === false ? PHP_INT_MAX : $priority;
            })
            ->values();
    }

    protected function formatSessionValue(string $setting, mixed $value, ?string $unit = null, array $settingConfig = []): ?string
    {
        if ($this->isBlankValue($value)) {
            return null;
        }

        $settingClass = ExerciseSetting::tryFrom($setting)?->settingClass();

        if (is_string($settingClass) && is_subclass_of($settingClass, AbstractSetting::class)) {
            return $settingClass::formatAthleteValue($value, $unit, $settingConfig);
        }

        return match ($setting) {
            'duration' => $this->formatDurationValue($value, $unit),
            'heartRateZone' => 'Zone '.trim((string) $value),
            default => $this->normalizeScalar($value),
        };
    }

    protected function resolveSettingLabel(string $setting, ?string $unit = null, mixed $exerciseConfig = null): string
    {
        $enum = ExerciseSetting::tryFrom($setting);
        $label = $enum?->shortLabel() ?? ucfirst($setting);
        $settingClass = $enum?->settingClass();
        $config = $this->resolveSettingConfig($exerciseConfig, $setting);

        if (is_string($settingClass) && is_subclass_of($settingClass, AbstractSetting::class)) {
            $label = $settingClass::athleteLabel($config);
        }

        if ($unit && ($enum?->showsUnitInLabel() ?? true)) {
            return "{$label} ({$unit})";
        }

        return $label;
    }

    protected function rowLabelClass(?string $color): string
    {
        return $color
            ? ColorPalette::light($color)
            : 'bg-zinc-50 dark:bg-zinc-800/20';
    }

    protected function cellClass(string $setting, mixed $value, ?string $rowColor, mixed $zoneValue = null, bool $isModified = false): string
    {
        $isDerivedHeartRateRange = $setting === 'heartRate'
            && is_string($value)
            && str_contains($value, '-')
            && $zoneValue !== null
            && $zoneValue !== '';

        $baseClass = $isModified
            ? ($rowColor ? ColorPalette::lightStrong($rowColor) : 'bg-zinc-200 dark:bg-zinc-600/50')
            : $this->rowLabelClass($rowColor);

        if ($setting === 'heartRateZone' || $isDerivedHeartRateRange) {
            $zone = trim((string) ($setting === 'heartRateZone' ? $value : $zoneValue));
            $zoneColors = new HeartRateZoneCellColors;

            return $isModified
                ? ($zoneColors->cellOverrideColor('heartRateZone', $zone) ?? $baseClass)
                : ($zoneColors->cellColor('heartRateZone', $zone) ?? $baseClass);
        }

        return $baseClass;
    }

    protected function normalizeScalar(mixed $value): string
    {
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_numeric($value) && str_contains((string) $value, '.')) {
            return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }

    protected function formatDurationValue(mixed $value, ?string $unit): string
    {
        if ($unit === 'mm:ss' && is_numeric($value)) {
            $totalSeconds = (int) $value;
            $minutes = intdiv($totalSeconds, 60);
            $seconds = $totalSeconds % 60;

            return sprintf('%d:%02d', $minutes, $seconds);
        }

        return $this->normalizeScalar($value);
    }

    protected function isBlankValue(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '' || $value === '-';
    }

    protected function bilateralRepsHintForValue(mixed $value, array $settingConfig): ?string
    {
        $canonical = RepsSetting::athleteCanonicalValue($value, $settingConfig);

        if (! ($canonical['is_bilateral'] ?? false)) {
            return null;
        }

        return RepsSetting::bilateralExecutionHint($canonical['bilateral_execution'] ?? null);
    }

    protected function resolveSettingConfig(mixed $exerciseConfig, string $setting): array
    {
        if (is_array($exerciseConfig)) {
            $settingConfig = is_array($exerciseConfig[$setting] ?? null)
                ? $exerciseConfig[$setting]
                : [];
            $settingConfig['_sets'] = is_array($exerciseConfig['sets'] ?? null)
                ? $exerciseConfig['sets']
                : [];

            return $settingConfig;
        }

        $settingData = is_object($exerciseConfig) ? $exerciseConfig->{$setting} ?? null : null;
        $setsData = is_object($exerciseConfig) ? $exerciseConfig->sets ?? null : null;
        $settingConfig = is_object($settingData) && method_exists($settingData, 'toArray')
            ? $settingData->toArray()
            : [];

        $settingConfig['_sets'] = is_object($setsData) && method_exists($setsData, 'toArray')
            ? $setsData->toArray()
            : [];

        return $settingConfig;
    }
}
