<?php

namespace App\Training;

use App\Data\Training\Compiled\CompiledTrainingExercise;
use App\Data\Training\Compiled\CompiledTrainingSet;
use App\Data\Training\Compiled\CompiledTrainingSetValue;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use Illuminate\Support\Facades\DB;

class TrainingSessionMaterializer
{
    public function __construct(
        private readonly TrainingSessionCompiler $compiler,
        private readonly TrainingValueSnapshotCodec $valueCodec,
    ) {}

    public function materialize(
        TrainingProgramSlot $slot,
        bool $force = false,
        bool $ignoreCompiledVersion = false,
        bool $allowImmutableRewrite = false,
    ): void
    {
        DB::transaction(function () use ($slot, $force, $ignoreCompiledVersion, $allowImmutableRewrite): void {
            $lockedSlot = TrainingProgramSlot::query()
                ->lockForUpdate()
                ->findOrFail($slot->id);

            $this->preserveCompilationRelations($slot, $lockedSlot);

            if ($this->shouldSkipMaterialization($lockedSlot, $force, $allowImmutableRewrite)) {
                return;
            }

            $compiled = $this->compiler->compile($lockedSlot);

            if (! $ignoreCompiledVersion && $this->isNoOpRebuild($lockedSlot, $compiled)) {
                return;
            }

            $lockedSlot->exercises()->delete();

            $valueRows = [];
            $timestamp = now();

            foreach ($compiled->exercises as $exercise) {
                $exerciseModel = $this->createExercise($lockedSlot, $exercise);

                foreach ($exercise->sets as $set) {
                    $setModel = $this->createSet($exerciseModel, $set);

                    foreach ($set->values as $value) {
                        $valueRows[] = [
                            'training_program_slot_set_id' => $setModel->id,
                            ...$this->encodeValueForInsert($value),
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }
                }
            }

            foreach (array_chunk($valueRows, 500) as $chunk) {
                DB::table('training_program_slot_set_values')->insert($chunk);
            }

            $lockedSlot->forceFill([
                'scheduled_date' => $compiled->scheduledDate,
                'compiled_at' => $timestamp,
                'compiled_version' => $compiled->compiledVersion,
                'status' => $lockedSlot->status ?? TrainingProgramSlotStatusEnum::Pending,
                'exercise_count' => count($compiled->exercises),
                'completed_exercise_count' => 0,
                'partial_exercise_count' => 0,
                'skipped_exercise_count' => 0,
                'pending_exercise_count' => count($compiled->exercises),
                'has_any_modification' => false,
            ])->saveQuietly();
        }, 5);
    }

    private function preserveCompilationRelations(TrainingProgramSlot $originalSlot, TrainingProgramSlot $lockedSlot): void
    {
        if ($originalSlot->relationLoaded('trainingProgram')) {
            $lockedSlot->setRelation('trainingProgram', $originalSlot->getRelation('trainingProgram'));
        }
    }

    private function shouldSkipMaterialization(TrainingProgramSlot $slot, bool $force, bool $allowImmutableRewrite): bool
    {
        if ($slot->compiled_at === null) {
            return false;
        }

        if ($slot->datetime->lte(now()) && ! $allowImmutableRewrite) {
            return true;
        }

        return ! $force;
    }

    private function isNoOpRebuild(TrainingProgramSlot $slot, \App\Data\Training\Compiled\CompiledTrainingSession $compiled): bool
    {
        if ($slot->compiled_at === null) {
            return false;
        }

        $scheduledDate = $slot->scheduled_date?->format('Y-m-d');

        return $slot->compiled_version === $compiled->compiledVersion
            && $scheduledDate === $compiled->scheduledDate;
    }

    private function createExercise(TrainingProgramSlot $slot, CompiledTrainingExercise $exercise): TrainingProgramSlotExercise
    {
        return $slot->exercises()->create([
            'exercise_id' => $exercise->exerciseId,
            'sort' => $exercise->sort,
            'group' => $exercise->group,
            'type' => $exercise->type,
            'status' => TrainingProgramSlotExerciseStatusEnum::Pending,
            'set_count' => count($exercise->sets),
            'completed_set_count' => 0,
            'modified_set_count' => 0,
            'skipped_set_count' => 0,
            'pending_set_count' => count($exercise->sets),
            'has_any_modification' => false,
        ]);
    }

    private function createSet(TrainingProgramSlotExercise $exerciseModel, CompiledTrainingSet $set): TrainingProgramSlotSet
    {
        return $exerciseModel->sets()->create([
            'set_number' => $set->setNumber,
            'status' => TrainingProgramSlotSetStatusEnum::Pending,
            'has_any_modification' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeValue(CompiledTrainingSetValue $value): array
    {
        return [
            'setting_key' => $value->settingKey,
            ...$this->valueCodec->encodePlannedValue($value),
            ...$this->valueCodec->clearActualValue(),
            'is_modified' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeValueForInsert(CompiledTrainingSetValue $value): array
    {
        return array_map(
            fn (mixed $columnValue): mixed => is_array($columnValue) ? json_encode($columnValue) : $columnValue,
            $this->encodeValue($value),
        );
    }
}
