<?php

namespace App\Training;

use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramSlot;
use App\Support\Training\ProgramExerciseOrder;
use Illuminate\Support\Facades\DB;

class TrainingGroupScheduleMirrorService
{
    public function __construct(
        private readonly ProgramExerciseOrder $programExerciseOrder,
    ) {}

    /**
     * @return array{
     *     mirrored_programs: int,
     *     mirrored_blocks: int,
     *     mirrored_slots: int
     * }
     */
    public function mirror(int $sourceGroupId, int $targetGroupId, bool $replace = false): array
    {
        return DB::transaction(function () use ($sourceGroupId, $targetGroupId, $replace): array {
            if ($replace) {
                $this->clearTargetGroup($targetGroupId);
            }

            $sourceBlocks = TrainingProgramBlock::query()
                ->where('group_id', $sourceGroupId)
                ->orderBy('id')
                ->get();

            $blockIdMap = [];

            foreach ($sourceBlocks as $sourceBlock) {
                $clone = $sourceBlock->replicate(['id']);
                $clone->group_id = $targetGroupId;
                $clone->parent_id = null;
                $clone->saveQuietly();

                $blockIdMap[$sourceBlock->id] = $clone->id;
            }

            foreach ($sourceBlocks as $sourceBlock) {
                $parentId = $sourceBlock->parent_id;

                if ($parentId === null || ! isset($blockIdMap[$sourceBlock->id], $blockIdMap[$parentId])) {
                    continue;
                }

                TrainingProgramBlock::query()
                    ->whereKey($blockIdMap[$sourceBlock->id])
                    ->update(['parent_id' => $blockIdMap[$parentId]]);
            }

            $sourcePrograms = TrainingProgram::query()
                ->with('program.exercises')
                ->where('group_id', $sourceGroupId)
                ->orderBy('sort')
                ->orderBy('id')
                ->get();

            $programIdMap = [];
            $programPivotIdMaps = [];

            foreach ($sourcePrograms as $sourceProgram) {
                $clone = TrainingProgram::importProgram($sourceProgram->program, $targetGroupId);
                $clone->forceFill([
                    'owner_id' => $sourceProgram->owner_id,
                    'sort' => $sourceProgram->sort,
                    'status' => $sourceProgram->status,
                    'planned_session_count' => $sourceProgram->planned_session_count,
                ])->save();

                $programIdMap[$sourceProgram->id] = $clone->id;
                $programPivotIdMaps[$sourceProgram->id] = $this->buildPivotIdMap($sourceProgram, $clone->fresh('program.exercises'));
            }

            $sourceSlots = TrainingProgramSlot::query()
                ->with('exercises.sets.values')
                ->whereIn('training_program_id', array_keys($programIdMap))
                ->orderBy('id')
                ->get();

            foreach ($sourceSlots as $sourceSlot) {
                $clone = $sourceSlot->replicate(['id']);
                $clone->training_program_id = $programIdMap[$sourceSlot->training_program_id];
                $clone->saveQuietly();

                $pivotIdMap = $programPivotIdMaps[$sourceSlot->training_program_id] ?? [];
                $this->cloneSlotSnapshot($sourceSlot, $clone, $pivotIdMap);
            }

            return [
                'mirrored_programs' => count($programIdMap),
                'mirrored_blocks' => count($blockIdMap),
                'mirrored_slots' => $sourceSlots->count(),
            ];
        });
    }

    private function clearTargetGroup(int $targetGroupId): void
    {
        $programs = TrainingProgram::query()
            ->where('group_id', $targetGroupId)
            ->get();

        foreach ($programs as $program) {
            $program->slots()->delete();
            $program->delete();
        }

        TrainingProgramBlock::query()
            ->where('group_id', $targetGroupId)
            ->delete();
    }

    /**
     * @return array<int, int>
     */
    private function buildPivotIdMap(TrainingProgram $sourceProgram, TrainingProgram $targetProgram): array
    {
        $sourceExercises = $this->programExerciseOrder
            ->sortProgramExercises($sourceProgram->program->exercises)
            ->values();

        $targetExercises = $this->programExerciseOrder
            ->sortProgramExercises($targetProgram->program->exercises)
            ->values();

        $map = [];

        foreach ($sourceExercises as $index => $sourceExercise) {
            $targetExercise = $targetExercises->get($index);

            if (! $targetExercise) {
                continue;
            }

            $map[(int) $sourceExercise->pivot->id] = (int) $targetExercise->pivot->id;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $pivotIdMap
     */
    private function cloneSlotSnapshot(TrainingProgramSlot $sourceSlot, TrainingProgramSlot $targetSlot, array $pivotIdMap): void
    {
        foreach ($sourceSlot->exercises as $sourceExercise) {
            $clonedExercise = $sourceExercise->replicate(['id']);
            $clonedExercise->training_program_slot_id = $targetSlot->id;

            if (isset($sourceExercise->exercise_program_exercise_id)) {
                $sourcePivotId = (int) $sourceExercise->exercise_program_exercise_id;
                $targetPivotId = $pivotIdMap[$sourcePivotId] ?? null;

                if ($targetPivotId !== null && $clonedExercise->isFillable('exercise_program_exercise_id')) {
                    $clonedExercise->exercise_program_exercise_id = $targetPivotId;
                }
            }

            $clonedExercise->saveQuietly();

            foreach ($sourceExercise->sets as $sourceSet) {
                $clonedSet = $sourceSet->replicate(['id']);
                $clonedSet->training_program_slot_exercise_id = $clonedExercise->id;
                $clonedSet->saveQuietly();

                foreach ($sourceSet->values as $sourceValue) {
                    $clonedValue = $sourceValue->replicate(['id']);
                    $clonedValue->training_program_slot_set_id = $clonedSet->id;
                    $clonedValue->saveQuietly();
                }
            }
        }
    }
}
