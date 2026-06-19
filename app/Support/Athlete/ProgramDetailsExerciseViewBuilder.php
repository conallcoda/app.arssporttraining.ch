<?php

namespace App\Support\Athlete;

use App\Data\Athlete\ProgramDetailsExerciseData;
use App\Data\Athlete\ProgramDetailsNoteData;
use App\Data\Athlete\ProgramDetailsSessionRowData;
use App\Data\Training\Snapshot\ScheduledExerciseSnapshotData;
use App\Data\Training\Snapshot\ScheduledSetSnapshotData;
use App\Data\Training\Snapshot\ScheduledValueSnapshotData;
use App\Support\Training\ScheduledSessionSnapshotBuilder;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Support\Athlete\Concerns\FormatsProgramDetailsExerciseValues;
use Coda\Cms\Support\ColorPalette;

class ProgramDetailsExerciseViewBuilder
{
    use FormatsProgramDetailsExerciseValues;

    public function buildFromSnapshot(ScheduledExerciseSnapshotData $exerciseSnapshot, int $index, ?string $groupLabel = null, array $pendingSkippedSetMap = []): ProgramDetailsExerciseData
    {
        $settingKeys = $this->orderedSettings(
            collect($exerciseSnapshot->sets)
                ->flatMap(fn (ScheduledSetSnapshotData $set) => collect($set->values)->pluck('settingKey'))
                ->unique()
                ->values()
                ->all()
        );

        $sessionRows = [];
        $sessionNotes = [];
        $bilateralRepsHint = null;
        $colorIndex = 0;

        foreach ($settingKeys as $setting) {
            if ($setting === 'note') {
                $notes = collect($exerciseSnapshot->sets)
                    ->map(function (ScheduledSetSnapshotData $set): mixed {
                        $value = collect($set->values)->firstWhere('settingKey', 'note');

                        return $value instanceof ScheduledValueSnapshotData ? $value->resolvedValue : null;
                    })
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
            $setIds = [];
            $firstUnit = null;
            $hasSettingRow = false;

            foreach ($exerciseSnapshot->sets as $set) {
                $setIds[] = $set->id;

                $isSkipped = array_key_exists($set->id, $pendingSkippedSetMap)
                    ? (bool) $pendingSkippedSetMap[$set->id]
                    : (string) ($set->status->value ?? $set->status) === TrainingProgramSlotSetStatusEnum::Skipped->value;

                if ($isSkipped) {
                    $values[] = 'Skipped';
                    $modifiedValues[] = false;
                    $valueClasses[] = 'bg-sky-100 text-sky-900 dark:bg-sky-900/30 dark:text-sky-200';
                    continue;
                }

                $value = collect($set->values)->firstWhere('settingKey', $setting);
                if ($value instanceof ScheduledValueSnapshotData && $firstUnit === null) {
                    $firstUnit = $value->unit;
                }
                $hasSettingRow = $hasSettingRow || $value instanceof ScheduledValueSnapshotData;

                $rawValue = $value instanceof ScheduledValueSnapshotData ? $value->resolvedValue : null;
                $zoneValue = $setting === 'heartRate'
                    ? (collect($set->values)->firstWhere('settingKey', 'heartRateZone')?->resolvedValue)
                    : null;
                $settingConfig = $this->resolveSettingConfig($exerciseSnapshot->settingConfigs, $setting);
                if ($setting === 'reps' && $bilateralRepsHint === null) {
                    $bilateralRepsHint = $this->bilateralRepsHintForValue($rawValue, $settingConfig);
                }
                $values[] = $this->formatSessionValue($setting, $rawValue, $value?->unit, $settingConfig);
                $modifiedValues[] = (bool) ($value?->isModified ?? false);
                $valueClasses[] = $this->cellClass(
                    $setting,
                    $rawValue,
                    $rowColorName,
                    $zoneValue,
                    $modifiedValues[array_key_last($modifiedValues)] ?? false
                );
            }

            if (! $hasSettingRow && collect($values)->every(fn (?string $value) => $value === null)) {
                continue;
            }

            $sessionRows[] = new ProgramDetailsSessionRowData(
                label: $this->resolveSettingLabel($setting, $firstUnit, $exerciseSnapshot->settingConfigs),
                settingKey: $setting,
                labelClass: $labelClass,
                values: $values,
                valueClasses: $valueClasses,
                modifiedValues: $modifiedValues,
                setIds: $setIds,
            );

            $colorIndex++;
        }

        return new ProgramDetailsExerciseData(
            id: $exerciseSnapshot->slotExerciseId,
            index: $index + 1,
            groupLabel: $groupLabel,
            name: $exerciseSnapshot->name,
            equipmentBadges: $exerciseSnapshot->equipmentBadges,
            modifierBadges: $exerciseSnapshot->modifierBadges,
            instructions: $exerciseSnapshot->instructions,
            bilateralRepsHint: $bilateralRepsHint,
            videoUrl: $exerciseSnapshot->videoUrl,
            photoUrls: $exerciseSnapshot->photoUrls,
            setLabel: $exerciseSnapshot->setLabel,
            setCount: count($exerciseSnapshot->sets),
            sessionRows: $sessionRows,
            weekDetails: [],
            notes: $sessionNotes,
            status: $exerciseSnapshot->status,
            statusLabel: $exerciseSnapshot->statusLabel,
            statusColor: $exerciseSnapshot->statusColor,
        );
    }

    public function build(TrainingProgramSlotExercise $slotExercise, int $index, ?string $groupLabel = null, array $pendingSkippedSetMap = []): ProgramDetailsExerciseData
    {
        return $this->buildFromSnapshot(
            app(ScheduledSessionSnapshotBuilder::class)->buildExercise($slotExercise),
            $index,
            $groupLabel,
            $pendingSkippedSetMap,
        );
    }
}
