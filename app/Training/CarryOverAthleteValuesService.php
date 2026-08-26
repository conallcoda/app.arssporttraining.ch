<?php

namespace App\Training;

use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Settings\AbstractSetting;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingPlanValueRevision;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Support\Training\ApplyPerScope;
use App\Support\Training\EffectiveSlotExerciseConfigResolver;
use App\Support\Training\GridOverrideNormalizer;
use App\Training\Planning\ExerciseSessionCoordinateResolver;
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
        private readonly TrainingSessionCompiler $sessionCompiler,
        private readonly ExerciseSessionCoordinateResolver $coordinateResolver,
        private readonly TrainingPlanRevisionService $revisionService,
    ) {}

    public function carryFrom(TrainingProgramSlotExercise $source): bool
    {
        return DB::transaction(function () use ($source): bool {
            $source = TrainingProgramSlotExercise::query()
                ->with(['slot.trainingProgram.program', 'exercise', 'settingSnapshot', 'sets.values'])
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
            $effectiveConfig = $this->currentEffectiveConfig($source);
            $block = $this->sessionCompiler->categoryBlockForSlot($source->slot);
            $nextCompletedAt = $this->nextCompletedSourceDateTime(
                $source,
                $block?->start,
                $block !== null ? ($block->end ?? $block->start) : null,
            );

            $targetSlots = TrainingProgramSlot::query()
                ->where('training_program_id', $source->slot->training_program_id)
                ->where('user_id', $source->slot->user_id)
                ->whereNull('cancelled_at')
                ->where('datetime', '>', $source->slot->datetime)
                ->where('datetime', '>', now())
                ->when($block !== null, fn ($query) => $query->whereBetween('datetime', [
                    $block->start->copy()->startOfDay(),
                    ($block->end ?? $block->start)->copy()->endOfDay(),
                ]))
                ->when($nextCompletedAt !== null, fn ($query) => $query->where('datetime', '<', $nextCompletedAt))
                ->with(['exercises' => fn ($query) => $query
                    ->where('exercise_program_exercise_id', $source->exercise_program_exercise_id)
                    ->with(['exercise', 'settingSnapshot', 'sets.values'])])
                ->orderBy('datetime')
                ->orderBy('id')
                ->get();

            foreach ($targetSlots as $targetSlot) {
                $target = $targetSlot->exercises->first();

                if ($target instanceof TrainingProgramSlotExercise && $this->hasRecordedActuals($target)) {
                    continue;
                }

                $position = $this->positionForSlot($targetSlot, $effectiveConfig);
                $targetChanges = $target instanceof TrainingProgramSlotExercise
                    ? $this->applyToTarget($source, $target, $sourceValues, $effectiveConfig, $position)
                    : $this->changesForUncompiledTarget($source, $sourceValues, $effectiveConfig, $position);
                $targetChanged = $targetChanges !== [];
                $changed = $targetChanged || $changed;

                if ($targetChanged) {
                    $gridOverrideChanges = array_merge(
                        $gridOverrideChanges,
                        $this->gridOverrideChangesForPosition($effectiveConfig, $position, $targetChanges),
                    );

                    if ($target instanceof TrainingProgramSlotExercise) {
                        $this->statusService->refreshExerciseState($target);
                    }
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

        $effectiveConfig = $this->currentEffectiveConfig($source);

        return $source->status === TrainingProgramSlotExerciseStatusEnum::Completed
            && $source->slot->status === TrainingProgramSlotStatusEnum::Completed
            && data_get($effectiveConfig, 'weight.mode', 'manual') === 'manual'
            && data_get($effectiveConfig, 'weight.carryOverAthleteValues', true) !== false;
    }

    /**
     * @return array<string, array<int, array{value: mixed, recorded_at: Carbon}>>
     */
    private function sourceValuesByField(TrainingProgramSlotExercise $source): array
    {
        $values = [];

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

                if (! $valueRow->actual_recorded_at instanceof Carbon) {
                    continue;
                }

                $values[$field][(int) $setIndex] = [
                    'value' => $actual,
                    'recorded_at' => $valueRow->actual_recorded_at,
                ];
            }
        }

        return array_filter($values, fn (array $fieldValues): bool => $fieldValues !== []);
    }

    /**
     * @param  array<string, array<int, array{value: mixed, recorded_at: Carbon}>>  $sourceValues
     * @param  array{week: int, session: int, usesChronologicalSessions: bool, usesGroupedSlotIndex: bool}  $position
     * @return list<array{field: string, set_index: int, value: mixed, recorded_at: Carbon}>
     */
    private function changesForUncompiledTarget(
        TrainingProgramSlotExercise $source,
        array $sourceValues,
        array $effectiveConfig,
        array $position,
    ): array {
        $changes = [];

        foreach ($sourceValues as $field => $fieldValues) {
            foreach ($fieldValues as $setIndex => $entry) {
                $revisionSet = $this->isSessionScoped($effectiveConfig, $field) ? null : (int) $setIndex;

                if ($this->hasLaterCoachPlan($source, $field, $position, $revisionSet, $entry['recorded_at'])) {
                    continue;
                }

                $changes[] = [
                    'field' => $field,
                    'set_index' => (int) $setIndex,
                    'value' => $entry['value'],
                    'recorded_at' => $entry['recorded_at'],
                ];
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, array<int, array{value: mixed, recorded_at: Carbon}>>  $sourceValues
     * @param  array{week: int, session: int, usesChronologicalSessions: bool, usesGroupedSlotIndex: bool}  $position
     * @return list<array{field: string, set_index: int, value: mixed, recorded_at: Carbon}>
     */
    private function applyToTarget(
        TrainingProgramSlotExercise $source,
        TrainingProgramSlotExercise $target,
        array $sourceValues,
        array $effectiveConfig,
        array $position,
    ): array {
        $changes = [];
        $sets = $target->sets->sortBy('set_number')->values();

        foreach ($sets as $setIndex => $set) {
            foreach ($sourceValues as $field => $fieldValues) {
                $valueRow = $set->values->firstWhere('setting_key', $field);

                if (! $valueRow instanceof TrainingProgramSlotSetValue) {
                    continue;
                }

                $sourceEntry = $fieldValues[$setIndex] ?? $this->lastSourceValue($fieldValues);
                $sourceValue = $sourceEntry['value'] ?? null;

                if ($sourceValue === null || $sourceValue === '') {
                    continue;
                }

                $revisionSet = $this->isSessionScoped($effectiveConfig, $field) ? null : (int) $setIndex;

                if ($this->hasLaterCoachPlan($source, $field, $position, $revisionSet, $sourceEntry['recorded_at'])) {
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
                    'recorded_at' => $sourceEntry['recorded_at'],
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
     * @param  array<int, array{value: mixed, recorded_at: Carbon}>  $fieldValues
     */
    private function lastSourceValue(array $fieldValues): ?array
    {
        if ($fieldValues === []) {
            return null;
        }

        ksort($fieldValues);

        $last = end($fieldValues);

        return is_array($last) ? $last : null;
    }

    /**
     * @param  array{week: int, session: int, usesChronologicalSessions: bool, usesGroupedSlotIndex: bool}  $position
     * @param  list<array{field: string, set_index: int, value: mixed, recorded_at: Carbon}>  $targetChanges
     * @return list<array{target: string, week: int, session: int, set?: int, field: string, value: mixed}>
     */
    private function gridOverrideChangesForPosition(array $effectiveConfig, array $position, array $targetChanges): array
    {
        $changes = [];
        $sessionScopedFieldsAdded = [];

        foreach ($targetChanges as $change) {
            $field = $change['field'];

            if ($this->isSessionScoped($effectiveConfig, $field)) {
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

    /** @return array{week: int, session: int, usesChronologicalSessions: bool, usesGroupedSlotIndex: bool} */
    private function positionForSlot(TrainingProgramSlot $slot, array $effectiveConfig): array
    {
        $context = $this->sessionCompiler->sessionContextForSlot($slot);

        return $this->coordinateResolver->resolve(
            effectiveConfig: $effectiveConfig,
            calendarWeekIndex: $context['weekIndex'],
            calendarSessionIndex: $context['sessionIndex'],
            slotIndex: $context['slotIndex'],
            useSlotIndexForGroupedSessions: true,
        );
    }

    private function isSessionScoped(array $effectiveConfig, string $field): bool
    {
        return ApplyPerScope::normalize(data_get($effectiveConfig, $field.'.applyPer')) === ApplyPerScope::SESSION;
    }

    /**
     * @param  array{week: int, session: int, usesChronologicalSessions: bool, usesGroupedSlotIndex: bool}  $position
     */
    private function hasLaterCoachPlan(
        TrainingProgramSlotExercise $source,
        string $field,
        array $position,
        ?int $setIndex,
        Carbon $actualRecordedAt,
    ): bool {
        $exerciseProgramId = (int) ($source->slot?->trainingProgram?->exercise_program_id ?? 0);

        if ($exerciseProgramId <= 0) {
            return false;
        }

        return TrainingPlanValueRevision::query()
            ->where('program_exercise_id', $source->exercise_program_exercise_id)
            ->where(fn ($query) => $query
                ->whereNull('user_id')
                ->orWhere('user_id', $source->slot?->user_id))
            ->where('setting_key', $field)
            ->where('week_index', $position['week'])
            ->where('session_index', $position['session'])
            ->when(
                $setIndex === null,
                fn ($query) => $query->whereNull('set_index'),
                fn ($query) => $query->where('set_index', $setIndex),
            )
            ->whereIn('source', ['coach', 'admin'])
            ->where('created_at', '>', $actualRecordedAt)
            ->where(function ($query) use ($exerciseProgramId): void {
                $query->where(function ($query) use ($exerciseProgramId): void {
                    $query->where('owner_type', ExerciseProgram::class)
                        ->where('owner_id', $exerciseProgramId);
                })->orWhere('owner_type', TrainingProgramSlotExercise::class);
            })
            ->exists();
    }

    private function nextCompletedSourceDateTime(
        TrainingProgramSlotExercise $source,
        ?Carbon $blockStart,
        ?Carbon $blockEnd,
    ): ?Carbon {
        $next = TrainingProgramSlotExercise::query()
            ->where('exercise_program_exercise_id', $source->exercise_program_exercise_id)
            ->where('status', TrainingProgramSlotExerciseStatusEnum::Completed)
            ->whereHas('slot', fn ($query) => $query
                ->where('training_program_id', $source->slot?->training_program_id)
                ->where('user_id', $source->slot?->user_id)
                ->where('status', TrainingProgramSlotStatusEnum::Completed)
                ->whereNull('cancelled_at')
                ->where('datetime', '>', $source->slot?->datetime)
                ->when($blockStart !== null, fn ($query) => $query->where('datetime', '>=', $blockStart->copy()->startOfDay()))
                ->when($blockEnd !== null, fn ($query) => $query->where('datetime', '<=', $blockEnd->copy()->endOfDay())))
            ->with('slot:id,datetime')
            ->get()
            ->sortBy(fn (TrainingProgramSlotExercise $exercise) => $exercise->slot?->datetime)
            ->first();

        return $next?->slot?->datetime;
    }

    /** @return array<string, mixed> */
    private function currentEffectiveConfig(TrainingProgramSlotExercise $source): array
    {
        $program = $source->slot?->trainingProgram?->program;
        $programConfig = $program?->config;

        if ($program === null
            || $source->exercise === null
            || ! is_object($programConfig)
            || ! method_exists($programConfig, 'resolveExercise')) {
            return $this->configResolver->resolve($source);
        }

        return $programConfig->resolveExercise(
            $source->exercise->config,
            (int) $source->exercise_program_exercise_id,
            (int) $source->slot->user_id,
        )->effectiveConfig;
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
        $beforeGridOverrides = $gridOverrides;

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

        $this->revisionService->recordGridOverrideChanges(
            owner: $exerciseProgram,
            programExerciseId: (int) $source->exercise_program_exercise_id,
            userId: (int) $source->slot->user_id,
            before: $beforeGridOverrides,
            after: $gridOverrides,
            action: 'carry_over_athlete_values',
            source: 'system',
        );
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
