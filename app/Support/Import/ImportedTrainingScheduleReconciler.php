<?php

namespace App\Support\Import;

use App\Training\TrainingProgramConfigOverrideNormalizer;
use Illuminate\Support\Facades\DB;

class ImportedTrainingScheduleReconciler
{
    public function __construct(
        private readonly SqlDumpRowReader $rowReader,
        private readonly TrainingProgramConfigOverrideNormalizer $overrideNormalizer,
    ) {}

    /**
     * @return array{
     *     missing_training_programs: int,
     *     restored_exercise_programs: int,
     *     restored_program_exercises: int,
     *     restored_training_programs: int,
     *     restored_blocks: int,
     *     restored_slots: int,
     *     restored_slot_exercises: int,
     *     restored_slot_sets: int,
     *     restored_slot_values: int,
     *     restored_metric_submissions: int,
     *     restored_metric_values: int,
     *     normalized_program_configs: int,
     *     affected_groups: array<int, int>
     * }
     */
    public function reconcile(string $dumpPath): array
    {
        $trainingPrograms = $this->rowReader->readRows($dumpPath, 'training_programs');
        $allDumpProgramIds = array_map(fn (array $row): int => (int) $row['id'], $trainingPrograms);
        $existingProgramIds = DB::table('training_programs')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $existingProgramLookup = array_fill_keys($existingProgramIds, true);

        $missingPrograms = array_values(array_filter(
            $trainingPrograms,
            fn (array $row): bool => ! isset($existingProgramLookup[(int) $row['id']]),
        ));

        $missingProgramIds = array_map(fn (array $row): int => (int) $row['id'], $missingPrograms);
        $affectedGroupIds = array_values(array_unique(array_map(fn (array $row): int => (int) $row['group_id'], $trainingPrograms)));
        $exerciseProgramIds = array_values(array_unique(array_map(fn (array $row): int => (int) $row['exercise_program_id'], $missingPrograms)));

        $exercisePrograms = $this->filterRowsByIds(
            $this->rowReader->readRows($dumpPath, 'exercise_programs'),
            $exerciseProgramIds,
            'id',
        );

        $programExercises = $this->filterRowsByIds(
            $this->rowReader->readRows($dumpPath, 'exercise_program_exercises'),
            $exerciseProgramIds,
            'exercise_program_id',
        );

        $blocks = array_values(array_filter(
            $this->rowReader->readRows($dumpPath, 'training_program_blocks'),
            fn (array $row): bool => in_array((int) $row['group_id'], $affectedGroupIds, true),
        ));

        $allContextSlots = $this->filterRowsByIds(
            $this->rowReader->readRows($dumpPath, 'training_program_slots'),
            $allDumpProgramIds,
            'training_program_id',
        );

        $slots = $this->filterRowsByIds(
            $allContextSlots,
            $missingProgramIds,
            'training_program_id',
        );

        $slotIds = array_map(fn (array $row): int => (int) $row['id'], $slots);

        $slotExercises = $this->filterRowsByIds(
            $this->rowReader->readRows($dumpPath, 'training_program_slot_exercises'),
            $slotIds,
            'training_program_slot_id',
        );

        $slotExerciseIds = array_map(fn (array $row): int => (int) $row['id'], $slotExercises);

        $slotSets = $this->filterRowsByIds(
            $this->rowReader->readRows($dumpPath, 'training_program_slot_sets'),
            $slotExerciseIds,
            'training_program_slot_exercise_id',
        );

        $slotSetIds = array_map(fn (array $row): int => (int) $row['id'], $slotSets);

        $slotValues = $this->filterRowsByIds(
            $this->rowReader->readRows($dumpPath, 'training_program_slot_set_values'),
            $slotSetIds,
            'training_program_slot_set_id',
        );

        $affectedUserIds = array_values(array_unique(array_map(fn (array $row): int => (int) $row['user_id'], $allContextSlots)));
        $metricSubmissions = $this->filterRowsByIds(
            $this->rowReader->readRows($dumpPath, 'user_metric_submissions'),
            $affectedUserIds,
            'user_id',
        );
        $metricSubmissionIds = array_map(fn (array $row): int => (int) $row['id'], $metricSubmissions);
        $metricValues = $this->filterRowsByIds(
            $this->rowReader->readRows($dumpPath, 'user_metric_values'),
            $metricSubmissionIds,
            'submission_id',
        );

        DB::transaction(function () use (
            $exercisePrograms,
            $programExercises,
            $missingPrograms,
            $blocks,
            $slots,
            $slotExercises,
            $slotSets,
            $slotValues,
            $metricSubmissions,
            $metricValues,
        ): void {
            $this->upsertTable('exercise_programs', $exercisePrograms);
            $this->upsertTable('exercise_program_exercises', $programExercises);
            $this->upsertTable('training_programs', $missingPrograms);
            $this->upsertTable('training_program_blocks', $blocks);
            $this->upsertTable('training_program_slots', $slots);
            $this->upsertTable('training_program_slot_exercises', $slotExercises);
            $this->upsertTable('training_program_slot_sets', $slotSets);
            $this->upsertTable('training_program_slot_set_values', $slotValues);
            $this->upsertTable('user_metric_submissions', $metricSubmissions);
            $this->upsertTable('user_metric_values', $metricValues);
        });

        $normalizedConfigs = 0;

        foreach ($allDumpProgramIds as $trainingProgramId) {
            $trainingProgram = \App\Models\Training\TrainingProgram::query()->find($trainingProgramId);

            if (! $trainingProgram) {
                continue;
            }

            if ($this->overrideNormalizer->normalize($trainingProgram)) {
                $normalizedConfigs++;
            }
        }

        return [
            'missing_training_programs' => count($missingPrograms),
            'restored_exercise_programs' => count($exercisePrograms),
            'restored_program_exercises' => count($programExercises),
            'restored_training_programs' => count($missingPrograms),
            'restored_blocks' => count($blocks),
            'restored_slots' => count($slots),
            'restored_slot_exercises' => count($slotExercises),
            'restored_slot_sets' => count($slotSets),
            'restored_slot_values' => count($slotValues),
            'restored_metric_submissions' => count($metricSubmissions),
            'restored_metric_values' => count($metricValues),
            'normalized_program_configs' => $normalizedConfigs,
            'affected_groups' => $affectedGroupIds,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function filterRowsByIds(array $rows, array $ids, string $column): array
    {
        if ($ids === []) {
            return [];
        }

        $lookup = array_fill_keys($ids, true);

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => isset($lookup[(int) $row[$column]]),
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function upsertTable(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $updateColumns = array_values(array_filter(
            array_keys($rows[0]),
            fn (string $column): bool => $column !== 'id',
        ));

        DB::table($table)->upsert($rows, ['id'], $updateColumns);
    }
}
