<?php

namespace App\Training;

use App\Data\Exercise\DropSet;
use App\Data\Exercise\ExerciseSetting;
use App\Models\Training\TrainingActualValueRevision;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Users\UserTypeEnum;
use App\Support\Training\EffectiveSlotExerciseConfigResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AthleteExerciseValueService
{
    public function __construct(
        private readonly TrainingSessionStatusService $statusService,
        private readonly TrainingValueSnapshotCodec $valueCodec,
        private readonly EffectiveSlotExerciseConfigResolver $configResolver,
    ) {}

    public function saveExerciseValues(TrainingProgramSlotExercise $exercise, array $submittedValues, bool $onlyProvided = false): bool
    {
        return DB::transaction(function () use ($exercise, $submittedValues, $onlyProvided): bool {
            $exercise = TrainingProgramSlotExercise::query()
                ->with(['slot', 'exercise', 'settingSnapshot', 'sets.values'])
                ->lockForUpdate()
                ->findOrFail($exercise->id);

            $hasChanges = false;
            $batch = null;

            foreach ($exercise->sets as $set) {
                foreach ($set->values as $valueRow) {
                    $settingClass = ExerciseSetting::tryFrom($valueRow->setting_key)?->settingClass();

                    if ($settingClass === null) {
                        continue;
                    }

                    $path = $set->id.'.'.$valueRow->setting_key;

                    if ($onlyProvided && ! Arr::has($submittedValues, $path)) {
                        continue;
                    }

                    $config = $this->resolveSettingConfig($exercise, $valueRow->setting_key);
                    $normalized = $settingClass::normalizeAthleteValue(
                        data_get($submittedValues, $path),
                        $config
                    );
                    $plannedValue = $this->valueCodec->extractPlannedValue($valueRow);
                    $isModified = ! $this->valuesEquivalent($normalized, $plannedValue);

                    $attributes = $this->buildActualSnapshotAttributes(
                        valueRow: $valueRow,
                        settingClass: $settingClass,
                        normalized: $normalized,
                        config: $config,
                        isModified: $isModified,
                    );

                    if ($this->rowNeedsUpdate($valueRow, $attributes)) {
                        $before = $this->revisionSnapshot($valueRow);
                        $valueRow->forceFill($attributes)->save();
                        $batch ??= $this->createRevisionBatch($exercise);
                        $this->recordActualRevision(
                            batch: $batch,
                            valueRow: $valueRow->fresh(),
                            before: $before,
                        );
                        $hasChanges = true;
                    }
                }

            }

            $this->statusService->refreshExerciseState($exercise);

            return $hasChanges;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSettingConfig(TrainingProgramSlotExercise $exercise, string $settingKey): array
    {
        $exerciseConfig = $this->configResolver->resolve($exercise);
        $setting = $exerciseConfig[$settingKey] ?? null;
        $sets = $exerciseConfig['sets'] ?? [];
        $expectedPartCount = DropSet::expectedPartCount($exerciseConfig);

        if (is_array($setting)) {
            $setting['_sets'] = is_array($sets) ? $sets : [];
            $setting['_drop_set_part_count'] = $expectedPartCount;

            return $setting;
        }

        $settingConfig = is_object($setting) && method_exists($setting, 'toArray')
            ? $setting->toArray()
            : [];
        $settingConfig['_sets'] = is_array($sets) ? $sets : [];
        $settingConfig['_drop_set_part_count'] = $expectedPartCount;

        return $settingConfig;
    }

    /**
     * @param  class-string  $settingClass
     * @return array<string, mixed>
     */
    private function buildActualSnapshotAttributes(
        TrainingProgramSlotSetValue $valueRow,
        string $settingClass,
        mixed $normalized,
        array $config,
        bool $isModified,
    ): array {
        if ($normalized === null) {
            return $this->valueCodec->clearActualValue() + [
                'is_modified' => false,
            ];
        }

        $valueType = $settingClass::athleteValueType($normalized, $config);
        $canonicalValue = $settingClass::athleteCanonicalValue($normalized, $config);

        return $this->valueCodec->encodeActualValue($valueType, $normalized, $canonicalValue) + [
            'actual_recorded_by' => auth()->id(),
            'actual_recorded_at' => now(),
            'actual_source' => $this->resolveActualSource(),
            'actual_is_explicit' => true,
            'unit' => $valueRow->unit,
            'is_modified' => $isModified,
        ];
    }

    private function valuesEquivalent(mixed $left, mixed $right): bool
    {
        if (is_float($left) || is_float($right)) {
            return (float) $left === (float) $right;
        }

        return $left === $right;
    }

    /**
     * @return array<string, mixed>
     */
    private function revisionSnapshot(TrainingProgramSlotSetValue $valueRow): array
    {
        return [
            'actual_value_type' => $valueRow->actual_value_type,
            'actual_int_value' => $valueRow->actual_int_value,
            'actual_decimal_value' => $valueRow->actual_decimal_value,
            'actual_string_value' => $valueRow->actual_string_value,
            'actual_json_value' => $valueRow->actual_json_value,
            'actual_is_explicit' => (bool) $valueRow->actual_is_explicit,
            'is_modified' => (bool) $valueRow->is_modified,
        ];
    }

    private function createRevisionBatch(TrainingProgramSlotExercise $exercise): TrainingRevisionBatch
    {
        $auditContext = app(TrainingAuditContext::class);

        return TrainingRevisionBatch::create([
            'owner_type' => TrainingProgramSlotExercise::class,
            'owner_id' => $exercise->id,
            'domain' => 'actual',
            'action' => 'record_actuals',
            'changed_by' => auth()->id(),
            'source' => $this->resolveActualSource(),
            'reason' => $auditContext->reason([
                'training_program_slot_id' => $exercise->training_program_slot_id,
                'training_program_slot_exercise_id' => $exercise->id,
            ]),
        ]);
    }

    private function recordActualRevision(TrainingRevisionBatch $batch, TrainingProgramSlotSetValue $valueRow, array $before): void
    {
        TrainingActualValueRevision::create([
            'batch_id' => $batch->id,
            'training_program_slot_set_value_id' => $valueRow->id,
            'recorded_by' => auth()->id(),
            'source' => $this->resolveActualSource(),
            'was_explicit' => (bool) ($before['actual_is_explicit'] ?? false),
            'is_explicit' => (bool) $valueRow->actual_is_explicit,
            'was_modified_from_plan' => (bool) ($before['is_modified'] ?? false),
            'is_modified_from_plan' => (bool) $valueRow->is_modified,
            'before_value_type' => $before['actual_value_type'] ?? null,
            'before_int_value' => $before['actual_int_value'] ?? null,
            'before_decimal_value' => $before['actual_decimal_value'] ?? null,
            'before_string_value' => $before['actual_string_value'] ?? null,
            'before_json_value' => $before['actual_json_value'] ?? null,
            'after_value_type' => $valueRow->actual_value_type,
            'after_int_value' => $valueRow->actual_int_value,
            'after_decimal_value' => $valueRow->actual_decimal_value,
            'after_string_value' => $valueRow->actual_string_value,
            'after_json_value' => $valueRow->actual_json_value,
            'unit' => $valueRow->unit,
        ]);
    }

    private function resolveActualSource(): string
    {
        $type = auth()->user()?->type;

        return match ($type) {
            UserTypeEnum::Coach => 'coach',
            UserTypeEnum::Admin => 'admin',
            default => 'athlete',
        };
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
