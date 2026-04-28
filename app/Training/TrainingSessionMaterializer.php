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
    ) {}

    public function materialize(TrainingProgramSlot $slot, bool $force = false): void
    {
        DB::transaction(function () use ($slot, $force): void {
            $slot = TrainingProgramSlot::query()
                ->lockForUpdate()
                ->findOrFail($slot->id);

            if ($this->shouldSkipMaterialization($slot, $force)) {
                return;
            }

            $compiled = $this->compiler->compile($slot);

            $slot->exercises()->delete();

            foreach ($compiled->exercises as $exercise) {
                $exerciseModel = $this->createExercise($slot, $exercise);

                foreach ($exercise->sets as $set) {
                    $setModel = $this->createSet($exerciseModel, $set);

                    foreach ($set->values as $value) {
                        $setModel->values()->create($this->encodeValue($value));
                    }
                }
            }

            $slot->forceFill([
                'scheduled_date' => $compiled->scheduledDate,
                'compiled_at' => now(),
                'compiled_version' => $compiled->compiledVersion,
                'status' => $slot->status ?? TrainingProgramSlotStatusEnum::Pending,
                'exercise_count' => count($compiled->exercises),
                'completed_exercise_count' => 0,
                'partial_exercise_count' => 0,
                'skipped_exercise_count' => 0,
                'pending_exercise_count' => count($compiled->exercises),
                'has_any_modification' => false,
            ])->saveQuietly();
        }, 5);
    }

    private function shouldSkipMaterialization(TrainingProgramSlot $slot, bool $force): bool
    {
        if ($slot->compiled_at === null) {
            return false;
        }

        if ($slot->datetime->lte(now())) {
            return true;
        }

        return ! $force;
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
        $row = [
            'setting_key' => $value->settingKey,
            'planned_value_type' => $value->plannedValueType,
            'actual_value_type' => null,
            'unit' => $value->unit,
            'is_modified' => false,
        ];

        return match ($value->plannedValueType) {
            'int' => $row + [
                'planned_int_value' => (int) $value->plannedValue,
            ],
            'decimal' => $row + [
                'planned_decimal_value' => (float) $value->plannedValue,
            ],
            'json' => $row + [
                'planned_json_value' => $value->plannedValue,
            ],
            default => $row + [
                'planned_string_value' => (string) $value->plannedValue,
            ],
        };
    }
}
