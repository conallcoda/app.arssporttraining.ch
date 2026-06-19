<?php

namespace App\Support\Athlete;

use App\Data\Athlete\ProgramDetailsExerciseData;
use App\Data\Athlete\ProgramDetailsNoteData;
use App\Data\Athlete\ProgramDetailsSessionRowData;
use App\Data\Training\Planned\ResolvedPlannedExercise;
use App\Models\Exercise\Exercise;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Support\Athlete\Concerns\FormatsProgramDetailsExerciseValues;
use Coda\Cms\Support\ColorPalette;
use Illuminate\Support\Facades\Schema;

class PlannedProgramDetailsExerciseViewBuilder
{
    use FormatsProgramDetailsExerciseValues;

    public function build(Exercise $exercise, ResolvedPlannedExercise $plannedExercise, int $index, ?string $groupLabel = null): ProgramDetailsExerciseData
    {
        $exerciseConfig = $exercise->config;
        $settingKeys = $this->orderedSettings(
            collect($plannedExercise->sets)
                ->flatMap(fn ($set) => collect($set->values)->pluck('settingKey'))
                ->all()
        );

        $sessionRows = [];
        $sessionNotes = [];
        $bilateralRepsHint = null;
        $colorIndex = 0;

        foreach ($settingKeys as $setting) {
            if ($setting === 'note') {
                $notes = collect($plannedExercise->sets)
                    ->map(fn ($set) => collect($set->values)->firstWhere('settingKey', 'note')?->value)
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
            $units = [];
            $hasSettingRow = false;

            foreach ($plannedExercise->sets as $set) {
                $valueRow = collect($set->values)->firstWhere('settingKey', $setting);
                $zoneValue = $setting === 'heartRate'
                    ? collect($set->values)->firstWhere('settingKey', 'heartRateZone')?->value
                    : null;
                $settingConfig = $this->resolveSettingConfig($exerciseConfig, $setting);
                if ($setting === 'reps' && $bilateralRepsHint === null) {
                    $bilateralRepsHint = $this->bilateralRepsHintForValue($valueRow?->value, $settingConfig);
                }
                $hasSettingRow = $hasSettingRow || $valueRow !== null;
                $units[] = $valueRow?->unit;
                $values[] = $this->formatSessionValue($setting, $valueRow?->value, $valueRow?->unit, $settingConfig);
                $valueClasses[] = $this->cellClass($setting, $valueRow?->value, $rowColorName, $zoneValue);
            }

            if (! $hasSettingRow && collect($values)->every(fn (?string $value) => $value === null)) {
                continue;
            }

            $sessionRows[] = new ProgramDetailsSessionRowData(
                label: $this->resolveSettingLabel($setting, collect($units)->first(fn (?string $unit) => $unit !== null), $exerciseConfig),
                settingKey: $setting,
                labelClass: $labelClass,
                values: $values,
                valueClasses: $valueClasses,
                modifiedValues: array_fill(0, count($values), false),
            );

            $colorIndex++;
        }

        return new ProgramDetailsExerciseData(
            id: (int) ($plannedExercise->exerciseId ?? $exercise->id),
            index: $index + 1,
            groupLabel: $groupLabel,
            name: $exercise->name ?? 'Exercise',
            equipmentBadges: $exercise->equipment?->pluck('name')->filter()->values()->all() ?? [],
            modifierBadges: $exercise->modifiers?->pluck('name')->filter()->values()->all() ?? [],
            instructions: $exercise->instructions,
            bilateralRepsHint: $bilateralRepsHint,
            videoUrl: $exercise->video_url,
            photoUrls: Schema::hasTable('media')
                ? $exercise->getMedia('photos')->map(fn ($media) => $media->getUrl())->values()->all()
                : [],
            setLabel: $exercise->config->sets->label ?? 'Set',
            setCount: count($plannedExercise->sets),
            sessionRows: $sessionRows,
            weekDetails: [],
            notes: $sessionNotes,
            status: TrainingProgramSlotExerciseStatusEnum::Pending,
            statusLabel: TrainingProgramSlotExerciseStatusEnum::Pending->label(),
            statusColor: TrainingProgramSlotExerciseStatusEnum::Pending->barColor(),
        );
    }
}
