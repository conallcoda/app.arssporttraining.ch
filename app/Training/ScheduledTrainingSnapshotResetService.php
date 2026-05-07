<?php

namespace App\Training;

use App\Data\Training\Compiled\CompiledTrainingExercise;
use App\Data\Training\Compiled\CompiledTrainingSet;
use App\Models\Exercise\ExercisePlanConfigOverride;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSet;
use Illuminate\Support\Facades\DB;

class ScheduledTrainingSnapshotResetService
{
    public function __construct(
        private readonly TrainingSessionCompiler $compiler,
        private readonly TrainingSessionStatusService $statusService,
        private readonly TrainingValueSnapshotCodec $valueCodec,
    ) {}

    /**
     * @return array{cleared_override_rows: int, reset_slots: int}
     */
    public function reset(?int $trainingProgramId = null, bool $clearAllPlanGridOverrides = true): array
    {
        $clearedOverrideRows = $this->clearPlanGridOverrides($trainingProgramId, $clearAllPlanGridOverrides);
        $resetSlots = 0;

        $slotQuery = TrainingProgramSlot::query()
            ->select('id')
            ->orderBy('id');

        if ($trainingProgramId !== null) {
            $slotQuery->where('training_program_id', $trainingProgramId);
        }

        $slotQuery->chunkById(100, function ($slots) use (&$resetSlots): void {
            foreach ($slots as $slot) {
                $this->resetSlot((int) $slot->id);
                $resetSlots++;
            }
        });

        return [
            'cleared_override_rows' => $clearedOverrideRows,
            'reset_slots' => $resetSlots,
        ];
    }

    private function clearPlanGridOverrides(?int $trainingProgramId, bool $clearAllPlanGridOverrides): int
    {
        $query = ExercisePlanConfigOverride::query();

        if (! $clearAllPlanGridOverrides) {
            $programIds = TrainingProgram::query()
                ->when($trainingProgramId !== null, fn ($builder) => $builder->whereKey($trainingProgramId))
                ->pluck('exercise_program_id')
                ->filter()
                ->values()
                ->all();

            $query->where('owner_type', ExerciseProgram::class)
                ->whereIn('owner_id', $programIds);
        } else {
            $query->where('owner_type', ExerciseProgram::class);
        }

        $count = (clone $query)->count();
        $query->delete();

        return $count;
    }

    private function resetSlot(int $slotId): void
    {
        DB::transaction(function () use ($slotId): void {
            $slot = TrainingProgramSlot::query()
                ->with(['trainingProgram.program.exercises', 'exercises.sets.values'])
                ->lockForUpdate()
                ->findOrFail($slotId);

            $compiled = $this->compiler->compile($slot);
            $matchedExerciseIds = [];

            foreach ($compiled->exercises as $compiledExercise) {
                $slotExercise = $this->matchOrCreateExercise($slot, $compiledExercise, $matchedExerciseIds);
                $matchedExerciseIds[] = $slotExercise->id;

                $this->syncSets($slotExercise, $compiledExercise);
            }

            if ($matchedExerciseIds === []) {
                $slot->exercises()->delete();
            } else {
                $slot->exercises()
                    ->whereNotIn('id', $matchedExerciseIds)
                    ->delete();
            }

            $slot->forceFill([
                'scheduled_date' => $compiled->scheduledDate,
                'compiled_at' => now(),
                'compiled_version' => $compiled->compiledVersion,
            ])->saveQuietly();

            $slot->load('exercises.sets.values');

            foreach ($slot->exercises as $exercise) {
                $this->statusService->refreshExerciseState($exercise);
            }
        }, 5);
    }

    /**
     * @param  array<int>  $matchedExerciseIds
     */
    private function matchOrCreateExercise(
        TrainingProgramSlot $slot,
        CompiledTrainingExercise $compiledExercise,
        array $matchedExerciseIds,
    ): TrainingProgramSlotExercise {
        $existing = $slot->exercises->first(function (TrainingProgramSlotExercise $exercise) use ($compiledExercise, $matchedExerciseIds): bool {
            if (in_array($exercise->id, $matchedExerciseIds, true)) {
                return false;
            }

            return (int) $exercise->exercise_id === $compiledExercise->exerciseId
                && (int) $exercise->sort === $compiledExercise->sort
                && (string) ($exercise->group ?? '') === (string) ($compiledExercise->group ?? '')
                && (string) ($exercise->type ?? 'main') === $compiledExercise->type;
        });

        if ($existing instanceof TrainingProgramSlotExercise) {
            $existing->forceFill([
                'exercise_id' => $compiledExercise->exerciseId,
                'sort' => $compiledExercise->sort,
                'group' => $compiledExercise->group,
                'type' => $compiledExercise->type,
            ])->saveQuietly();

            $existing->load('sets.values');

            return $existing;
        }

        return $slot->exercises()->create([
            'exercise_id' => $compiledExercise->exerciseId,
            'sort' => $compiledExercise->sort,
            'group' => $compiledExercise->group,
            'type' => $compiledExercise->type,
            'status' => 'pending',
            'set_count' => count($compiledExercise->sets),
            'completed_set_count' => 0,
            'modified_set_count' => 0,
            'skipped_set_count' => 0,
            'pending_set_count' => count($compiledExercise->sets),
            'has_any_modification' => false,
        ]);
    }

    private function syncSets(TrainingProgramSlotExercise $slotExercise, CompiledTrainingExercise $compiledExercise): void
    {
        $existingSets = $slotExercise->sets->keyBy('set_number');
        $setNumbers = [];

        foreach ($compiledExercise->sets as $compiledSet) {
            $setNumbers[] = $compiledSet->setNumber;

            /** @var TrainingProgramSlotSet $set */
            $set = $existingSets->get($compiledSet->setNumber)
                ?? $slotExercise->sets()->create([
                    'set_number' => $compiledSet->setNumber,
                    'status' => 'pending',
                    'has_any_modification' => false,
                ]);

            $set->loadMissing('values');
            $this->syncSetValues($set, $compiledSet);
        }

        if ($setNumbers === []) {
            $slotExercise->sets()->delete();
        } else {
            $slotExercise->sets()
                ->whereNotIn('set_number', $setNumbers)
                ->delete();
        }
    }

    private function syncSetValues(TrainingProgramSlotSet $set, CompiledTrainingSet $compiledSet): void
    {
        $existingValues = $set->values->keyBy('setting_key');
        $settingKeys = [];

        foreach ($compiledSet->values as $compiledValue) {
            $settingKeys[] = $compiledValue->settingKey;

            $attributes = $this->valueCodec->encodePlannedValue($compiledValue)
                + $this->valueCodec->clearActualValue()
                + ['is_modified' => false];

            $valueRow = $existingValues->get($compiledValue->settingKey);

            if ($valueRow) {
                $valueRow->forceFill($attributes)->save();
                continue;
            }

            $set->values()->create([
                'setting_key' => $compiledValue->settingKey,
                ...$attributes,
            ]);
        }

        if ($settingKeys === []) {
            $set->values()->delete();
        } else {
            $set->values()
                ->whereNotIn('setting_key', $settingKeys)
                ->delete();
        }
    }
}
