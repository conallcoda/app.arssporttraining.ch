<?php

namespace App\Training;

use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Models\Users\UserTypeEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TrainingSessionProgressService
{
    public function __construct(
        private readonly TrainingSessionStatusService $statusService,
        private readonly TrainingValueSnapshotCodec $valueCodec,
        private readonly TrainingStateRevisionService $stateRevisionService,
    ) {}

    public function markExerciseCompleted(TrainingProgramSlotExercise $exercise, bool $completeSkippedSets = false): void
    {
        DB::transaction(function () use ($exercise, $completeSkippedSets): void {
            $exercise = TrainingProgramSlotExercise::query()
                ->with(['slot', 'sets.values'])
                ->lockForUpdate()
                ->findOrFail($exercise->id);

            $beforeExercise = $this->stateSnapshot($exercise);
            $beforeSlot = $this->stateSnapshot($exercise->slot);
            $beforeSets = $exercise->sets->mapWithKeys(
                fn (TrainingProgramSlotSet $set): array => [$set->id => $this->stateSnapshot($set)]
            )->all();

            $now = now();

            foreach ($exercise->sets as $set) {
                if ($set->skipped_at !== null && ! $completeSkippedSets) {
                    $set->forceFill([
                        'completed_at' => null,
                    ])->save();

                    continue;
                }

                $set->forceFill([
                    'completed_at' => $now,
                    'skipped_at' => null,
                ])->save();

                $this->materializePlannedValuesAsActuals($set->fresh('values') ?? $set, $now);
            }

            $this->statusService->refreshExerciseState($exercise);
            $this->recordProgressStateChanges(
                owner: $exercise,
                action: 'mark_exercise_completed',
                beforeSets: $beforeSets,
                beforeExercise: $beforeExercise,
                beforeSlot: $beforeSlot,
            );
        });
    }

    public function markExerciseSkipped(TrainingProgramSlotExercise $exercise): void
    {
        DB::transaction(function () use ($exercise): void {
            $exercise = TrainingProgramSlotExercise::query()
                ->with(['slot', 'sets.values'])
                ->lockForUpdate()
                ->findOrFail($exercise->id);

            $beforeExercise = $this->stateSnapshot($exercise);
            $beforeSlot = $this->stateSnapshot($exercise->slot);
            $beforeSets = $exercise->sets->mapWithKeys(
                fn (TrainingProgramSlotSet $set): array => [$set->id => $this->stateSnapshot($set)]
            )->all();

            $now = now();

            $exercise->sets()->update([
                'completed_at' => null,
                'skipped_at' => $now,
            ]);

            $this->statusService->refreshExerciseState($exercise);
            $this->recordProgressStateChanges(
                owner: $exercise,
                action: 'mark_exercise_skipped',
                beforeSets: $beforeSets,
                beforeExercise: $beforeExercise,
                beforeSlot: $beforeSlot,
            );
        });
    }

    public function markExercisePending(TrainingProgramSlotExercise $exercise): void
    {
        DB::transaction(function () use ($exercise): void {
            $exercise = TrainingProgramSlotExercise::query()
                ->with(['slot', 'sets.values'])
                ->lockForUpdate()
                ->findOrFail($exercise->id);

            $beforeExercise = $this->stateSnapshot($exercise);
            $beforeSlot = $this->stateSnapshot($exercise->slot);
            $beforeSets = $exercise->sets->mapWithKeys(
                fn (TrainingProgramSlotSet $set): array => [$set->id => $this->stateSnapshot($set)]
            )->all();

            $exercise->sets()->update([
                'completed_at' => null,
                'skipped_at' => null,
            ]);

            $this->statusService->refreshExerciseState($exercise);
            $this->recordProgressStateChanges(
                owner: $exercise,
                action: 'mark_exercise_pending',
                beforeSets: $beforeSets,
                beforeExercise: $beforeExercise,
                beforeSlot: $beforeSlot,
            );
        });
    }

    public function markSetSkipped(TrainingProgramSlotSet $set): void
    {
        DB::transaction(function () use ($set): void {
            $set = TrainingProgramSlotSet::query()
                ->with(['slotExercise.slot', 'values'])
                ->lockForUpdate()
                ->findOrFail($set->id);

            $beforeSet = $this->stateSnapshot($set);
            $beforeExercise = $this->stateSnapshot($set->slotExercise);
            $beforeSlot = $this->stateSnapshot($set->slotExercise->slot);

            foreach ($set->values as $valueRow) {
                $valueRow->forceFill($this->valueCodec->clearActualValue() + [
                    'is_modified' => false,
                ])->save();
            }

            $set->forceFill([
                'completed_at' => null,
                'skipped_at' => now(),
            ])->save();

            $this->statusService->refreshExerciseState($set->slotExercise);
            $this->recordProgressStateChanges(
                owner: $set,
                action: 'mark_set_skipped',
                beforeSets: [$set->id => $beforeSet],
                beforeExercise: $beforeExercise,
                beforeSlot: $beforeSlot,
            );
        });
    }

    public function markSetPending(TrainingProgramSlotSet $set): void
    {
        DB::transaction(function () use ($set): void {
            $set = TrainingProgramSlotSet::query()
                ->with(['slotExercise.slot', 'values'])
                ->lockForUpdate()
                ->findOrFail($set->id);

            $beforeSet = $this->stateSnapshot($set);
            $beforeExercise = $this->stateSnapshot($set->slotExercise);
            $beforeSlot = $this->stateSnapshot($set->slotExercise->slot);

            $set->forceFill([
                'completed_at' => null,
                'skipped_at' => null,
            ])->save();

            $this->statusService->refreshExerciseState($set->slotExercise);
            $this->recordProgressStateChanges(
                owner: $set,
                action: 'mark_set_pending',
                beforeSets: [$set->id => $beforeSet],
                beforeExercise: $beforeExercise,
                beforeSlot: $beforeSlot,
            );
        });
    }

    private function materializePlannedValuesAsActuals(TrainingProgramSlotSet $set, Carbon $recordedAt): void
    {
        $set->loadMissing('values');

        foreach ($set->values as $valueRow) {
            if ($valueRow->actual_value_type !== null || $valueRow->planned_value_type === null) {
                continue;
            }

            $valueRow->forceFill($this->plannedActualAttributes($valueRow, $recordedAt))->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function plannedActualAttributes(TrainingProgramSlotSetValue $valueRow, Carbon $recordedAt): array
    {
        return [
            'actual_value_type' => $valueRow->planned_value_type,
            'actual_int_value' => $valueRow->planned_int_value,
            'actual_decimal_value' => $valueRow->planned_decimal_value,
            'actual_string_value' => $valueRow->planned_string_value,
            'actual_json_value' => $valueRow->planned_json_value,
            'actual_recorded_by' => auth()->id(),
            'actual_recorded_at' => $recordedAt,
            'actual_source' => $this->resolveActualSource(),
            'actual_is_explicit' => true,
            'is_modified' => false,
        ];
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
     * @param  array<int, array<string, mixed>>  $beforeSets
     * @param  array<string, mixed>  $beforeExercise
     * @param  array<string, mixed>  $beforeSlot
     */
    private function recordProgressStateChanges(
        TrainingProgramSlotExercise|TrainingProgramSlotSet $owner,
        string $action,
        array $beforeSets,
        array $beforeExercise,
        array $beforeSlot,
    ): void {
        $exercise = $owner instanceof TrainingProgramSlotSet ? $owner->slotExercise : $owner;
        $afterExercise = $exercise->fresh(['slot', 'sets.values']);
        $afterSlot = $afterExercise->slot;
        $afterSets = $afterExercise->sets->keyBy('id');
        $batch = $this->stateRevisionService->createBatch($owner, $action);
        $hasRows = false;

        foreach ($beforeSets as $setId => $beforeSet) {
            $afterSet = $afterSets->get($setId);

            if (! $afterSet) {
                continue;
            }

            $afterSetSnapshot = $this->stateSnapshot($afterSet);
            $row = $this->stateRevisionService->recordStateChange(
                batch: $batch,
                subject: $afterSet,
                stateKey: 'status',
                beforeValue: $beforeSet['status'],
                afterValue: $afterSetSnapshot['status'],
                beforePayload: $beforeSet,
                afterPayload: $afterSetSnapshot,
            );

            $hasRows = $hasRows || $row !== null;
        }

        $afterExerciseSnapshot = $this->stateSnapshot($afterExercise);
        $exerciseRow = $this->stateRevisionService->recordStateChange(
            batch: $batch,
            subject: $afterExercise,
            stateKey: 'status',
            beforeValue: $beforeExercise['status'],
            afterValue: $afterExerciseSnapshot['status'],
            beforePayload: $beforeExercise,
            afterPayload: $afterExerciseSnapshot,
        );
        $hasRows = $hasRows || $exerciseRow !== null;

        $afterSlotSnapshot = $this->stateSnapshot($afterSlot);
        $slotRow = $this->stateRevisionService->recordStateChange(
            batch: $batch,
            subject: $afterSlot,
            stateKey: 'status',
            beforeValue: $beforeSlot['status'],
            afterValue: $afterSlotSnapshot['status'],
            beforePayload: $beforeSlot,
            afterPayload: $afterSlotSnapshot,
        );
        $hasRows = $hasRows || $slotRow !== null;

        if (! $hasRows) {
            $batch->delete();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function stateSnapshot(TrainingProgramSlotSet|TrainingProgramSlotExercise|TrainingProgramSlot $subject): array
    {
        $base = [
            'status' => $subject->status?->value ?? (string) $subject->status,
            'completed_at' => $subject->completed_at?->toIso8601String(),
        ];

        if ($subject instanceof TrainingProgramSlotSet) {
            return $base + [
                'skipped_at' => $subject->skipped_at?->toIso8601String(),
                'has_any_modification' => (bool) $subject->has_any_modification,
            ];
        }

        if ($subject instanceof TrainingProgramSlotExercise) {
            return $base + [
                'set_count' => (int) $subject->set_count,
                'completed_set_count' => (int) $subject->completed_set_count,
                'modified_set_count' => (int) $subject->modified_set_count,
                'skipped_set_count' => (int) $subject->skipped_set_count,
                'pending_set_count' => (int) $subject->pending_set_count,
                'has_any_modification' => (bool) $subject->has_any_modification,
            ];
        }

        return $base + [
            'exercise_count' => (int) $subject->exercise_count,
            'completed_exercise_count' => (int) $subject->completed_exercise_count,
            'partial_exercise_count' => (int) $subject->partial_exercise_count,
            'skipped_exercise_count' => (int) $subject->skipped_exercise_count,
            'pending_exercise_count' => (int) $subject->pending_exercise_count,
            'has_any_modification' => (bool) $subject->has_any_modification,
            'cancelled_at' => $subject->cancelled_at?->toIso8601String(),
        ];
    }
}
