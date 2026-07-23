<?php

namespace App\Training;

use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Settings\AbstractSetting;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Support\Training\ApplyPerScope;
use App\Support\Training\EffectiveSlotExerciseConfigResolver;
use App\Support\Training\GridOverrideNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CarryOverAthleteValuesService
{
    private const CARRIED_FIELDS = ['weight', 'reps', 'rest', 'tempo'];

    public function __construct(
        private readonly EffectiveSlotExerciseConfigResolver $configResolver,
        private readonly TrainingSessionPlannedValueService $plannedValueService,
        private readonly TrainingSessionStatusService $statusService,
        private readonly TrainingValueSnapshotCodec $valueCodec,
    ) {}

    public function carryFrom(TrainingProgramSlotExercise $source): bool
    {
        return DB::transaction(function () use ($source): bool {
            $source = TrainingProgramSlotExercise::query()
                ->with(['slot', 'exercise', 'settingSnapshot', 'sets.values'])
                ->lockForUpdate()
                ->findOrFail($source->id);

            if (! $this->sourceQualifies($source)) {
                return false;
            }

            $sourceValues = $this->sourceValuesByField($source);

            if ($sourceValues === []) {
                return false;
            }

            $changed = false;
            $gridOverrideChanges = [];
            $schedulePositions = $this->schedulePositions($source);

            $targets = TrainingProgramSlotExercise::query()
                ->where('exercise_program_exercise_id', $source->exercise_program_exercise_id)
                ->whereHas('slot', fn ($query) => $query
                    ->where('training_program_id', $source->slot->training_program_id)
                    ->where('user_id', $source->slot->user_id)
                    ->whereNull('cancelled_at')
                    ->where('datetime', '>', $source->slot->datetime)
                )
                ->with(['slot', 'exercise', 'settingSnapshot', 'sets.values'])
                ->orderBy(
                    DB::raw('(select datetime from training_program_slots where training_program_slots.id = training_program_slot_exercises.training_program_slot_id)')
                )
                ->get();

            foreach ($targets as $target) {
                if ($this->hasRecordedActuals($target)) {
                    continue;
                }

                $targetChanges = $this->applyToTarget($target, $sourceValues);
                $targetChanged = $targetChanges !== [];
                $changed = $targetChanged || $changed;

                if ($targetChanged) {
                    $gridOverrideChanges = array_merge(
                        $gridOverrideChanges,
                        $this->gridOverrideChangesForTarget($target, $targetChanges, $schedulePositions),
                    );
                    $this->statusService->refreshExerciseState($target);
                }
            }

            if ($gridOverrideChanges !== []) {
                $this->persistGridOverrides($source, $gridOverrideChanges);
            }

            return $changed;
        });
    }

    private function sourceQualifies(TrainingProgramSlotExercise $source): bool
    {
        if ($source->exercise_program_exercise_id === null || $source->slot === null) {
            return false;
        }

        if ($source->slot->training_program_id === null || $source->slot->user_id === null || $source->slot->datetime === null) {
            return false;
        }

        $effectiveConfig = $this->configResolver->resolve($source);

        return data_get($effectiveConfig, 'weight.mode', 'manual') === 'manual'
            && data_get($effectiveConfig, 'weight.carryOverAthleteValues', true) !== false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function sourceValuesByField(TrainingProgramSlotExercise $source): array
    {
        $values = [];
        $hasModifiedValue = [];

        foreach ($source->sets->sortBy('set_number')->values() as $setIndex => $set) {
            foreach (self::CARRIED_FIELDS as $field) {
                $valueRow = $set->values->firstWhere('setting_key', $field);

                if (! $this->isExplicitActual($valueRow)) {
                    continue;
                }

                $actual = $this->valueCodec->extractActualValue($valueRow);

                if ($actual === null || $actual === '') {
                    continue;
                }

                $values[$field][(int) $setIndex] = $actual;

                if (! $this->valuesEquivalent($actual, $this->valueCodec->extractPlannedValue($valueRow))) {
                    $hasModifiedValue[$field] = true;
                }
            }
        }

        return array_filter(
            $values,
            fn (array $fieldValues, string $field): bool => $fieldValues !== [] && ($hasModifiedValue[$field] ?? false),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param  array<string, array<int, mixed>>  $sourceValues
     * @return list<array{field: string, set_index: int, value: mixed}>
     */
    private function applyToTarget(TrainingProgramSlotExercise $target, array $sourceValues): array
    {
        $changes = [];
        $sets = $target->sets->sortBy('set_number')->values();

        foreach ($sets as $setIndex => $set) {
            foreach ($sourceValues as $field => $fieldValues) {
                $valueRow = $set->values->firstWhere('setting_key', $field);

                if (! $valueRow instanceof TrainingProgramSlotSetValue) {
                    continue;
                }

                $sourceValue = $fieldValues[$setIndex] ?? $this->lastSourceValue($fieldValues);

                if ($sourceValue === null || $sourceValue === '') {
                    continue;
                }

                $attributes = $this->plannedValueService->buildPlannedSnapshotAttributes(
                    $target,
                    $valueRow,
                    $sourceValue,
                );

                $changes[] = [
                    'field' => $field,
                    'set_index' => (int) $setIndex,
                    'value' => $sourceValue,
                ];

                if (! $this->rowNeedsUpdate($valueRow, $attributes)) {
                    continue;
                }

                $valueRow->forceFill($attributes)->save();
            }
        }

        return $changes;
    }

    /**
     * @param  array<int, mixed>  $fieldValues
     */
    private function lastSourceValue(array $fieldValues): mixed
    {
        if ($fieldValues === []) {
            return null;
        }

        ksort($fieldValues);

        return end($fieldValues);
    }

    /**
     * @return array<string, array{week: int, session: int}>
     */
    private function schedulePositions(TrainingProgramSlotExercise $source): array
    {
        $slots = TrainingProgramSlot::query()
            ->where('training_program_id', $source->slot->training_program_id)
            ->where('user_id', $source->slot->user_id)
            ->whereNull('cancelled_at')
            ->orderBy('datetime')
            ->orderBy('id')
            ->get(['datetime']);

        $positions = [];

        $slots
            ->groupBy(fn ($slot): string => Carbon::parse($slot->datetime)->isoWeekYear().'-'.Carbon::parse($slot->datetime)->isoWeek())
            ->values()
            ->each(function (Collection $weekSlots, int $weekIndex) use (&$positions): void {
                $weekSlots->values()->each(function ($slot, int $sessionIndex) use (&$positions, $weekIndex): void {
                    $positions[Carbon::parse($slot->datetime)->toDateString()] = [
                        'week' => $weekIndex,
                        'session' => $sessionIndex,
                    ];
                });
            });

        return $positions;
    }

    /**
     * @param  list<array{field: string, set_index: int, value: mixed}>  $targetChanges
     * @param  array<string, array{week: int, session: int}>  $schedulePositions
     * @return list<array{target: string, week: int, session: int, set?: int, field: string, value: mixed}>
     */
    private function gridOverrideChangesForTarget(TrainingProgramSlotExercise $target, array $targetChanges, array $schedulePositions): array
    {
        $date = $target->slot?->datetime?->toDateString();

        if ($date === null || ! isset($schedulePositions[$date])) {
            return [];
        }

        $position = $schedulePositions[$date];
        $changes = [];
        $sessionScopedFieldsAdded = [];

        foreach ($targetChanges as $change) {
            $field = $change['field'];

            if ($this->isSessionScoped($target, $field)) {
                if (isset($sessionScopedFieldsAdded[$field])) {
                    continue;
                }

                $sessionScopedFieldsAdded[$field] = true;
                $changes[] = [
                    'target' => 'session',
                    'week' => $position['week'],
                    'session' => $position['session'],
                    'field' => $field,
                    'value' => $change['value'],
                ];

                continue;
            }

            $changes[] = [
                'target' => 'cell',
                'week' => $position['week'],
                'session' => $position['session'],
                'set' => $change['set_index'],
                'field' => $field,
                'value' => $change['value'],
            ];
        }

        return $changes;
    }

    private function isSessionScoped(TrainingProgramSlotExercise $target, string $field): bool
    {
        $config = $this->configResolver->resolve($target);

        return ApplyPerScope::normalize(data_get($config, $field.'.applyPer')) === ApplyPerScope::SESSION;
    }

    /**
     * @param  list<array{target: string, week: int, session: int, set?: int, field: string, value: mixed}>  $changes
     */
    private function persistGridOverrides(TrainingProgramSlotExercise $source, array $changes): void
    {
        $exerciseProgram = ExerciseProgram::query()->find($source->slot?->trainingProgram?->exercise_program_id);

        if (! $exerciseProgram instanceof ExerciseProgram) {
            return;
        }

        $config = $exerciseProgram->config;
        $overrides = $config->exerciseOverrides(
            (int) $source->exercise_program_exercise_id,
            (int) $source->slot->user_id,
        );
        $gridOverrides = GridOverrideNormalizer::normalize($overrides->gridOverrides);

        foreach ($changes as $change) {
            if ($change['target'] === 'session') {
                $gridOverrides['sessions'] = GridOverrideNormalizer::putSessionValue(
                    $gridOverrides['sessions'] ?? [],
                    $change['week'],
                    $change['session'],
                    $change['field'],
                    $change['value'],
                );
            } else {
                $gridOverrides['cells'] = GridOverrideNormalizer::putCellValue(
                    $gridOverrides['cells'] ?? [],
                    $change['week'],
                    $change['session'],
                    (int) ($change['set'] ?? 0),
                    $change['field'],
                    $change['value'],
                );
            }
        }

        $overrides->gridOverrides = $gridOverrides;
        $config->setExerciseOverrides(
            (int) $source->exercise_program_exercise_id,
            $overrides,
            (int) $source->slot->user_id,
        );

        $exerciseProgram->config = $config;
        $exerciseProgram->saveQuietly();
    }

    private function hasRecordedActuals(TrainingProgramSlotExercise $exercise): bool
    {
        return $exercise->sets
            ->flatMap(fn (TrainingProgramSlotSet $set): Collection => $set->values)
            ->contains(fn (TrainingProgramSlotSetValue $value): bool => $value->actual_value_type !== null || (bool) $value->actual_is_explicit);
    }

    private function isExplicitActual(?TrainingProgramSlotSetValue $valueRow): bool
    {
        if (! $valueRow instanceof TrainingProgramSlotSetValue) {
            return false;
        }

        $settingClass = ExerciseSetting::tryFrom($valueRow->setting_key)?->settingClass();

        return is_string($settingClass)
            && is_subclass_of($settingClass, AbstractSetting::class)
            && $valueRow->actual_value_type !== null
            && (bool) $valueRow->actual_is_explicit;
    }

    private function valuesEquivalent(mixed $left, mixed $right): bool
    {
        if (is_float($left) || is_float($right)) {
            return (float) $left === (float) $right;
        }

        return $left === $right;
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
