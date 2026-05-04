<?php

namespace App\Support\Athlete;

use App\Data\Athlete\ProgramDetailsExerciseData;
use App\Data\Athlete\ProgramDetailsNoteData;
use App\Data\Athlete\ProgramDetailsSessionRowData;
use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Settings\AbstractSetting;
use App\Data\Exercise\Strategies\HeartRate\HeartRateZoneCellColors;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSetValue;
use Coda\Cms\Support\ColorPalette;
use Illuminate\Support\Collection;

class ProgramDetailsExerciseViewBuilder
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

    public function build(TrainingProgramSlotExercise $slotExercise, int $index, ?string $groupLabel = null): ProgramDetailsExerciseData
    {
        $exercise = $slotExercise->exercise;
        $exerciseConfig = $exercise?->config;
        $sets = $slotExercise->sets->sortBy('set_number')->values();
        $settingKeys = $this->orderedSettings(
            $sets->flatMap(fn ($set) => $set->values->pluck('setting_key'))
                ->unique()
                ->values()
                ->all()
        );

        $sessionRows = [];
        $sessionNotes = [];
        $colorIndex = 0;

        foreach ($settingKeys as $setting) {
            if ($setting === 'note') {
                $notes = $sets
                    ->map(fn ($set) => $this->extractResolvedValue($set->values->firstWhere('setting_key', 'note')))
                    ->filter(fn ($value) => ! $this->isBlankValue($value))
                    ->unique()
                    ->values();

                if ($notes->isNotEmpty()) {
                    $sessionNotes[] = new ProgramDetailsNoteData(
                        label: 'Note',
                        value: $notes->implode(' / '),
                    );
                }

                continue;
            }

            $rowColorName = ColorPalette::ROW_COLORS[$colorIndex] ?? null;
            $labelClass = $this->rowLabelClass($rowColorName);
            $values = [];
            $valueClasses = [];
            $modifiedValues = [];
            $firstValueRow = null;

            foreach ($sets as $set) {
                $valueRow = $set->values->firstWhere('setting_key', $setting);
                if ($valueRow instanceof TrainingProgramSlotSetValue && $firstValueRow === null) {
                    $firstValueRow = $valueRow;
                }

                $rawValue = $this->extractResolvedValue($valueRow);
                $zoneValue = $setting === 'heartRate'
                    ? $this->extractResolvedValue($set->values->firstWhere('setting_key', 'heartRateZone'))
                    : null;
                $settingConfig = $this->resolveSettingConfig($exerciseConfig, $setting);
                $values[] = $this->formatSessionValue($setting, $rawValue, $valueRow, $settingConfig);
                $modifiedValues[] = (bool) ($valueRow?->is_modified ?? false);
                $valueClasses[] = $this->cellClass(
                    $setting,
                    $rawValue,
                    $rowColorName,
                    $zoneValue,
                    $modifiedValues[array_key_last($modifiedValues)] ?? false
                );
            }

            if (collect($values)->every(fn (?string $value) => $value === null)) {
                continue;
            }

            $sessionRows[] = new ProgramDetailsSessionRowData(
                label: $this->resolveMaterializedSettingLabel($setting, $firstValueRow, $exerciseConfig),
                labelClass: $labelClass,
                values: $values,
                valueClasses: $valueClasses,
                modifiedValues: $modifiedValues,
            );

            $colorIndex++;
        }

        return new ProgramDetailsExerciseData(
            id: $slotExercise->id,
            index: $index + 1,
            groupLabel: $groupLabel,
            name: $exercise?->name ?? 'Exercise',
            equipmentBadges: $exercise?->equipment?->pluck('name')->filter()->values()->all() ?? [],
            modifierBadges: $exercise?->modifiers?->pluck('name')->filter()->values()->all() ?? [],
            instructions: $exercise?->instructions,
            videoUrl: $exercise?->video_url,
            photoUrls: $exercise?->getMedia('photos')->map(fn ($media) => $media->getUrl())->values()->all() ?? [],
            setLabel: $exercise?->config->sets->label ?? 'Set',
            setCount: $sets->count(),
            sessionRows: $sessionRows,
            weekDetails: [],
            notes: $sessionNotes,
            status: $slotExercise->status,
            statusLabel: $slotExercise->status->label(),
            statusColor: $slotExercise->status->barColor(),
        );
    }

    protected function formatSessionValue(string $setting, mixed $value, ?TrainingProgramSlotSetValue $valueRow = null, array $settingConfig = []): ?string
    {
        if ($this->isBlankValue($value)) {
            return null;
        }

        $settingClass = ExerciseSetting::tryFrom($setting)?->settingClass();

        if (is_string($settingClass) && is_subclass_of($settingClass, AbstractSetting::class)) {
            return $settingClass::formatAthleteValue($value, $valueRow?->unit, $settingConfig);
        }

        return match ($setting) {
            'duration' => $this->formatDurationValue($value, $valueRow?->unit),
            'heartRateZone' => 'Zone '.trim((string) $value),
            default => $this->normalizeScalar($value),
        };
    }

    protected function resolveMaterializedSettingLabel(string $setting, ?TrainingProgramSlotSetValue $valueRow, mixed $exerciseConfig = null): string
    {
        $enum = ExerciseSetting::tryFrom($setting);
        $label = $enum?->shortLabel() ?? ucfirst($setting);
        $settingClass = $enum?->settingClass();
        $config = $this->resolveSettingConfig($exerciseConfig, $setting);

        if (is_string($settingClass) && is_subclass_of($settingClass, AbstractSetting::class)) {
            $label = $settingClass::athleteLabel($config);
        }

        $unit = $valueRow?->unit;

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

    protected function extractPlannedValue(?TrainingProgramSlotSetValue $valueRow): mixed
    {
        if (! $valueRow) {
            return null;
        }

        return match ($valueRow->planned_value_type) {
            'int' => $valueRow->planned_int_value,
            'decimal' => $valueRow->planned_decimal_value !== null ? (float) $valueRow->planned_decimal_value : null,
            'json' => $valueRow->planned_json_value,
            default => $valueRow->planned_string_value,
        };
    }

    protected function extractResolvedValue(?TrainingProgramSlotSetValue $valueRow): mixed
    {
        if (! $valueRow) {
            return null;
        }

        if ($valueRow->actual_value_type !== null) {
            return match ($valueRow->actual_value_type) {
                'int' => $valueRow->actual_int_value,
                'decimal' => $valueRow->actual_decimal_value !== null ? (float) $valueRow->actual_decimal_value : null,
                'json' => $valueRow->actual_json_value,
                default => $valueRow->actual_string_value,
            };
        }

        return $this->extractPlannedValue($valueRow);
    }

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

    protected function isBlankValue(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '' || $value === '-';
    }

    protected function resolveSettingConfig(mixed $exerciseConfig, string $setting): array
    {
        $settingData = is_object($exerciseConfig) ? $exerciseConfig->{$setting} ?? null : null;

        return is_object($settingData) && method_exists($settingData, 'toArray')
            ? $settingData->toArray()
            : [];
    }
}
