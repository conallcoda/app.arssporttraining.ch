<?php

namespace App\Training;

use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\UserGroup;
use App\Support\Training\ProgramExerciseOrder;
use Illuminate\Support\Collection;
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
            $targetGroup = UserGroup::query()->findOrFail($targetGroupId);
            $audit = app(TrainingScheduleAuditService::class);
            $beforeSlots = $this->targetGroupSlots($targetGroupId);
            $beforeDefinitions = $this->targetGroupDefinitionPayload($targetGroupId);
            $batch = $audit->start($targetGroup, 'mirror_group_schedule', [
                'source_group_id' => $sourceGroupId,
                'target_group_id' => $targetGroupId,
                'replace' => $replace,
                'before_slot_ids' => $beforeSlots->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ]);

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

            $result = [
                'mirrored_programs' => count($programIdMap),
                'mirrored_blocks' => count($blockIdMap),
                'mirrored_slots' => $sourceSlots->count(),
            ];

            $audit->recordChanges($batch, $beforeSlots, $this->targetGroupSlots($targetGroupId));
            app(TrainingModelAuditService::class)->recordPayloadChange(
                owner: $targetGroup,
                domain: 'definition',
                action: 'mirror_group_definitions',
                stateKey: 'definition',
                beforePayload: $beforeDefinitions,
                afterPayload: $this->targetGroupDefinitionPayload($targetGroupId),
                context: [
                    'source_group_id' => $sourceGroupId,
                    'target_group_id' => $targetGroupId,
                    'replace' => $replace,
                ],
            );

            return $result;
        });
    }

    /** @return Collection<int, TrainingProgramSlot> */
    private function targetGroupSlots(int $groupId): Collection
    {
        return TrainingProgramSlot::query()
            ->whereHas('trainingProgram', fn ($query) => $query->where('group_id', $groupId))
            ->orderBy('id')
            ->get();
    }

    /** @return array<string, mixed> */
    private function targetGroupDefinitionPayload(int $groupId): array
    {
        return [
            'programs' => TrainingProgram::query()
                ->with('program')
                ->where('group_id', $groupId)
                ->orderBy('id')
                ->get()
                ->map(fn (TrainingProgram $program): array => [
                    'id' => (int) $program->id,
                    'exercise_program_id' => (int) $program->exercise_program_id,
                    'name' => $program->program?->name,
                    'sort' => (int) $program->sort,
                    'status' => $program->status,
                    'planned_session_count' => $program->planned_session_count,
                    'owner_id' => $program->owner_id,
                ])
                ->all(),
            'blocks' => TrainingProgramBlock::query()
                ->where('group_id', $groupId)
                ->orderBy('id')
                ->get()
                ->map(fn (TrainingProgramBlock $block): array => [
                    'id' => (int) $block->id,
                    'user_id' => $block->user_id,
                    'category_id' => $block->category_id,
                    'parent_id' => $block->parent_id,
                    'type' => $block->type?->value ?? (string) $block->type,
                    'start' => $block->start?->format('Y-m-d'),
                    'end' => $block->end?->format('Y-m-d'),
                    'note' => $block->note,
                    'color' => $block->color,
                    'active' => (bool) $block->active,
                ])
                ->all(),
        ];
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
            ->get()
            ->each(fn (TrainingProgramBlock $block) => $block->delete());
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
