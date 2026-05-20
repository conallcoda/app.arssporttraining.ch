<?php

namespace App\Support\Training;

use App\Data\Training\Snapshot\ScheduledExerciseSnapshotData;
use App\Data\Training\Snapshot\ScheduledSessionSnapshotData;
use App\Data\Training\Snapshot\ScheduledSetSnapshotData;
use App\Data\Training\Snapshot\ScheduledValueSnapshotData;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetValue;

class ScheduledSessionSnapshotBuilder
{
    public function __construct(
        private readonly ProgramExerciseOrder $programExerciseOrder,
    ) {}

    public function build(TrainingProgramSlot $slot): ScheduledSessionSnapshotData
    {
        $slot->loadMissing([
            'exercises.exercise.equipment',
            'exercises.exercise.modifiers',
            'exercises.exercise.media',
            'exercises.sets.values',
        ]);

        return new ScheduledSessionSnapshotData(
            slotId: (int) $slot->id,
            scheduledDate: ($slot->scheduled_date ?? $slot->datetime)->format('Y-m-d'),
            exercises: $this->programExerciseOrder
                ->sortSlotExercises($slot->exercises)
                ->values()
                ->map(fn (TrainingProgramSlotExercise $exercise): ScheduledExerciseSnapshotData => $this->buildExercise($exercise))
                ->all(),
        );
    }

    public function buildExercise(TrainingProgramSlotExercise $slotExercise): ScheduledExerciseSnapshotData
    {
        $slotExercise->loadMissing([
            'exercise.equipment',
            'exercise.modifiers',
            'exercise.media',
            'sets.values',
        ]);

        $exercise = $slotExercise->exercise;

        return new ScheduledExerciseSnapshotData(
            slotExerciseId: (int) $slotExercise->id,
            exerciseId: (int) ($slotExercise->exercise_id ?? $exercise?->id ?? 0),
            sort: (int) $slotExercise->sort,
            group: $slotExercise->group,
            type: (string) ($slotExercise->type ?? 'main'),
            name: $exercise?->name ?? 'Exercise',
            equipmentBadges: $exercise?->equipment?->pluck('name')->filter()->values()->all() ?? [],
            modifierBadges: $exercise?->modifiers?->pluck('name')->filter()->values()->all() ?? [],
            instructions: $exercise?->instructions,
            videoUrl: $exercise?->video_url,
            photoUrls: $exercise?->getMedia('photos')->map(fn ($media) => $media->getUrl())->values()->all() ?? [],
            setLabel: $exercise?->config->sets->label ?? 'Set',
            settingConfigs: $this->extractSettingConfigs($exercise?->config),
            sets: $slotExercise->sets
                ->sortBy('set_number')
                ->values()
                ->map(fn (TrainingProgramSlotSet $set): ScheduledSetSnapshotData => $this->buildSet($set))
                ->all(),
            status: $slotExercise->status,
            statusLabel: $slotExercise->status->label(),
            statusColor: $slotExercise->status->barColor(),
        );
    }

    private function buildSet(TrainingProgramSlotSet $set): ScheduledSetSnapshotData
    {
        return new ScheduledSetSnapshotData(
            id: (int) $set->id,
            setNumber: (int) $set->set_number,
            status: $set->status,
            hasAnyModification: (bool) $set->has_any_modification,
            values: $set->values
                ->sortBy('setting_key')
                ->values()
                ->map(fn (TrainingProgramSlotSetValue $value): ScheduledValueSnapshotData => $this->buildValue($value))
                ->all(),
        );
    }

    private function buildValue(TrainingProgramSlotSetValue $value): ScheduledValueSnapshotData
    {
        $plannedValue = $this->extractValue(
            type: $value->planned_value_type,
            intValue: $value->planned_int_value,
            decimalValue: $value->planned_decimal_value,
            stringValue: $value->planned_string_value,
            jsonValue: $value->planned_json_value,
        );
        $actualValue = $this->extractValue(
            type: $value->actual_value_type,
            intValue: $value->actual_int_value,
            decimalValue: $value->actual_decimal_value,
            stringValue: $value->actual_string_value,
            jsonValue: $value->actual_json_value,
        );

        return new ScheduledValueSnapshotData(
            id: (int) $value->id,
            settingKey: $value->setting_key,
            plannedValue: $plannedValue,
            actualValue: $actualValue,
            resolvedValue: $actualValue ?? $plannedValue,
            unit: $value->unit,
            isModified: (bool) $value->is_modified,
        );
    }

    private function extractValue(
        ?string $type,
        ?int $intValue,
        ?string $decimalValue,
        ?string $stringValue,
        mixed $jsonValue,
    ): mixed {
        return match ($type) {
            'int' => $intValue,
            'decimal' => $decimalValue !== null ? (float) $decimalValue : null,
            'json' => $jsonValue,
            'string' => $stringValue,
            default => null,
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function extractSettingConfigs(mixed $exerciseConfig): array
    {
        if (! is_object($exerciseConfig) || ! method_exists($exerciseConfig, 'toArray')) {
            return [];
        }

        $configArray = $exerciseConfig->toArray();
        $settings = collect($configArray['settings'] ?? [])
            ->filter(fn ($setting) => is_string($setting) && $setting !== '')
            ->values();

        return $settings
            ->mapWithKeys(fn (string $setting): array => [$setting => is_array($configArray[$setting] ?? null) ? $configArray[$setting] : []])
            ->all();
    }
}
