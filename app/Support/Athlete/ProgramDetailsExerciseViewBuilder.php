<?php

namespace App\Support\Athlete;

use App\Data\Athlete\ProgramDetailsExerciseData;
use App\Data\Athlete\ProgramDetailsNoteData;
use App\Data\Athlete\ProgramDetailsSessionRowData;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Support\Athlete\Concerns\FormatsProgramDetailsExerciseValues;
use Coda\Cms\Support\ColorPalette;
use Illuminate\Support\Collection;

class ProgramDetailsExerciseViewBuilder
{
    use FormatsProgramDetailsExerciseValues;

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
                $values[] = $this->formatSessionValue($setting, $rawValue, $valueRow?->unit, $settingConfig);
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
                label: $this->resolveSettingLabel($setting, $firstValueRow?->unit, $exerciseConfig),
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

}
