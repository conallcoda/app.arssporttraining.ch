<?php

namespace App\Console\Commands;

use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgramSlotExercise;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AuditSlotExerciseIdentityCommand extends Command
{
    protected $signature = 'training:audit-slot-exercise-identity
        {--training-program-id= : Limit the audit to a single scheduled training program}
        {--exercise-program-id= : Limit the audit to a single exercise program}
        {--user-id= : Limit the audit to a single athlete}
        {--slot-id=* : Limit the audit to one or more specific slot ids}
        {--show=25 : Number of example rows to show for missing, ambiguous, or mismatched records}
        {--json : Output the summary and examples as JSON}';

    protected $description = 'Audits whether materialized slot exercises can be mapped back to their program exercise pivot rows.';

    /** @var array<string, array<int, array<string, array<int, int>>>> */
    private array $indexesByModeAndProgram = [];

    /** @var array<int, array<int, object>> */
    private array $pivotRowsByProgram = [];

    /** @var array<int, int> */
    private array $pivotProgramIds = [];

    public function handle(): int
    {
        $hasDirectIdentityColumn = Schema::hasColumn('training_program_slot_exercises', 'exercise_program_exercise_id');
        $showLimit = max(0, (int) $this->option('show'));
        $examples = [
            'loose_missing' => [],
            'loose_ambiguous' => [],
            'strict_missing_but_unique_loose' => [],
            'strict_ambiguous' => [],
            'direct_missing' => [],
            'direct_mismatch' => [],
            'program_duplicate_signatures' => [],
        ];
        $summary = [
            'slot_exercises_audited' => 0,
            'strict_unique_matches' => 0,
            'strict_missing_matches' => 0,
            'strict_ambiguous_matches' => 0,
            'loose_unique_matches' => 0,
            'loose_missing_matches' => 0,
            'loose_ambiguous_matches' => 0,
            'missing_strict_but_unique_loose' => 0,
            'strict_ambiguous_but_unique_loose' => 0,
            'direct_identity_column_exists' => $hasDirectIdentityColumn,
            'direct_identity_present' => 0,
            'direct_identity_valid' => 0,
            'direct_identity_missing_pivot' => 0,
            'direct_identity_program_mismatch' => 0,
            'direct_identity_loose_signature_mismatch' => 0,
            'direct_identity_strict_signature_mismatch' => 0,
            'exercise_programs_seen' => 0,
            'exercise_programs_with_duplicate_strict_signatures' => 0,
            'duplicate_strict_program_signatures' => 0,
            'exercise_programs_with_duplicate_loose_signatures' => 0,
            'duplicate_loose_program_signatures' => 0,
        ];

        $programIdsSeen = [];
        $programDuplicateStrictSignatureCounts = [];
        $programDuplicateLooseSignatureCounts = [];

        $this->query()
            ->with([
                'slot.trainingProgram.program',
            ])
            ->orderBy('id')
            ->chunkById(500, function (Collection $slotExercises) use (
                &$summary,
                &$examples,
                &$programIdsSeen,
                &$programDuplicateStrictSignatureCounts,
                &$programDuplicateLooseSignatureCounts,
                $hasDirectIdentityColumn,
                $showLimit,
            ): void {
                foreach ($slotExercises as $slotExercise) {
                    if (! $slotExercise instanceof TrainingProgramSlotExercise) {
                        continue;
                    }

                    $summary['slot_exercises_audited']++;

                    $exerciseProgramId = (int) ($slotExercise->slot?->trainingProgram?->exercise_program_id ?? 0);

                    if ($exerciseProgramId <= 0) {
                        $summary['strict_missing_matches']++;
                        $summary['loose_missing_matches']++;
                        $this->pushExample($examples['loose_missing'], $this->exampleRow($slotExercise, reason: 'No exercise program for slot'), $showLimit);

                        continue;
                    }

                    $programIdsSeen[$exerciseProgramId] = true;
                    $duplicateStrictCount = $this->duplicateSignatureCount($exerciseProgramId, 'strict');
                    if ($duplicateStrictCount > 0) {
                        $programDuplicateStrictSignatureCounts[$exerciseProgramId] = $duplicateStrictCount;
                        $this->pushProgramDuplicateExamples($examples['program_duplicate_signatures'], $exerciseProgramId, $showLimit, 'strict');
                    }

                    $duplicateLooseCount = $this->duplicateSignatureCount($exerciseProgramId, 'loose');
                    if ($duplicateLooseCount > 0) {
                        $programDuplicateLooseSignatureCounts[$exerciseProgramId] = $duplicateLooseCount;
                        $this->pushProgramDuplicateExamples($examples['program_duplicate_signatures'], $exerciseProgramId, $showLimit, 'loose');
                    }

                    $strictSignature = $this->strictSignature(
                        exerciseId: (int) ($slotExercise->exercise_id ?? 0),
                        sort: (int) ($slotExercise->sort ?? 0),
                        group: $slotExercise->group,
                        type: (string) ($slotExercise->type ?? 'main'),
                    );
                    $strictMatches = $this->signatureIndex($exerciseProgramId, 'strict')[$strictSignature] ?? [];

                    $looseSignature = $this->looseSignature(
                        exerciseId: (int) ($slotExercise->exercise_id ?? 0),
                        type: (string) ($slotExercise->type ?? 'main'),
                    );
                    $looseMatches = $this->signatureIndex($exerciseProgramId, 'loose')[$looseSignature] ?? [];

                    if (count($strictMatches) === 0) {
                        $summary['strict_missing_matches']++;
                    } elseif (count($strictMatches) === 1) {
                        $summary['strict_unique_matches']++;
                    } else {
                        $summary['strict_ambiguous_matches']++;
                        $this->pushExample($examples['strict_ambiguous'], $this->exampleRow(
                            $slotExercise,
                            reason: 'Multiple current pivot rows have this strict signature',
                            matchingPivotIds: $strictMatches,
                        ), $showLimit);
                    }

                    if (count($looseMatches) === 0) {
                        $summary['loose_missing_matches']++;
                        $this->pushExample($examples['loose_missing'], $this->exampleRow($slotExercise, reason: 'No current pivot row has this exercise id and type'), $showLimit);
                    } elseif (count($looseMatches) === 1) {
                        $summary['loose_unique_matches']++;
                    } else {
                        $summary['loose_ambiguous_matches']++;
                        $this->pushExample($examples['loose_ambiguous'], $this->exampleRow(
                            $slotExercise,
                            reason: 'Multiple current pivot rows have this exercise id and type',
                            matchingPivotIds: $looseMatches,
                        ), $showLimit);
                    }

                    if (count($strictMatches) === 0 && count($looseMatches) === 1) {
                        $summary['missing_strict_but_unique_loose']++;
                        $this->pushExample($examples['strict_missing_but_unique_loose'], $this->exampleRow(
                            $slotExercise,
                            reason: 'Strict lookup misses, but exercise id and type has one match',
                            matchingPivotIds: $looseMatches,
                        ), $showLimit);
                    }

                    if (count($strictMatches) > 1 && count($looseMatches) === 1) {
                        $summary['strict_ambiguous_but_unique_loose']++;
                    }

                    if ($hasDirectIdentityColumn) {
                        $this->auditDirectIdentity(
                            $slotExercise,
                            $exerciseProgramId,
                            $strictSignature,
                            $looseSignature,
                            $summary,
                            $examples,
                            $showLimit,
                        );
                    }
                }
            });

        $summary['exercise_programs_seen'] = count($programIdsSeen);
        $summary['exercise_programs_with_duplicate_strict_signatures'] = count($programDuplicateStrictSignatureCounts);
        $summary['duplicate_strict_program_signatures'] = array_sum($programDuplicateStrictSignatureCounts);
        $summary['exercise_programs_with_duplicate_loose_signatures'] = count($programDuplicateLooseSignatureCounts);
        $summary['duplicate_loose_program_signatures'] = array_sum($programDuplicateLooseSignatureCounts);

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'summary' => $summary,
                'examples' => $examples,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->renderReport($summary, $examples);

        return self::SUCCESS;
    }

    private function query(): Builder
    {
        return TrainingProgramSlotExercise::query()
            ->when($this->filledOption('training-program-id'), function (Builder $query): void {
                $query->whereHas('slot', fn (Builder $slotQuery) => $slotQuery
                    ->where('training_program_id', (int) $this->option('training-program-id')));
            })
            ->when($this->filledOption('exercise-program-id'), function (Builder $query): void {
                $query->whereHas('slot.trainingProgram', fn (Builder $programQuery) => $programQuery
                    ->where('exercise_program_id', (int) $this->option('exercise-program-id')));
            })
            ->when($this->filledOption('user-id'), function (Builder $query): void {
                $query->whereHas('slot', fn (Builder $slotQuery) => $slotQuery
                    ->where('user_id', (int) $this->option('user-id')));
            })
            ->when($this->slotIds() !== [], function (Builder $query): void {
                $query->whereIn('training_program_slot_id', $this->slotIds());
            });
    }

    /** @return array<int, int> */
    private function slotIds(): array
    {
        return collect((array) $this->option('slot-id'))
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();
    }

    private function filledOption(string $name): bool
    {
        $value = $this->option($name);

        return $value !== null && $value !== '';
    }

    /** @return array<string, array<int, int>> */
    /**
     * @return array<string, array<int, int>>
     */
    private function signatureIndex(int $exerciseProgramId, string $mode): array
    {
        if (! isset($this->indexesByModeAndProgram[$mode][$exerciseProgramId])) {
            $index = [];

            foreach ($this->pivotRows($exerciseProgramId) as $pivot) {
                $signature = $mode === 'loose'
                    ? $this->looseSignature(
                        exerciseId: (int) $pivot->exercise_id,
                        type: (string) ($pivot->type ?? 'main'),
                    )
                    : $this->strictSignature(
                        exerciseId: (int) $pivot->exercise_id,
                        sort: (int) ($pivot->sort ?? 0),
                        group: $pivot->group,
                        type: (string) ($pivot->type ?? 'main'),
                    );

                $index[$signature][] = (int) $pivot->id;
                $this->pivotProgramIds[(int) $pivot->id] = $exerciseProgramId;
            }

            $this->indexesByModeAndProgram[$mode][$exerciseProgramId] = $index;
        }

        return $this->indexesByModeAndProgram[$mode][$exerciseProgramId];
    }

    /** @return array<int, object> */
    private function pivotRows(int $exerciseProgramId): array
    {
        if (! isset($this->pivotRowsByProgram[$exerciseProgramId])) {
            $this->pivotRowsByProgram[$exerciseProgramId] = ExerciseProgramExercise::query()
                ->where('exercise_program_id', $exerciseProgramId)
                ->orderBy('id')
                ->get(['id', 'exercise_program_id', 'exercise_id', 'sort', 'group', 'type'])
                ->all();
        }

        return $this->pivotRowsByProgram[$exerciseProgramId];
    }

    private function duplicateSignatureCount(int $exerciseProgramId, string $mode): int
    {
        return collect($this->signatureIndex($exerciseProgramId, $mode))
            ->filter(fn (array $pivotIds): bool => count($pivotIds) > 1)
            ->count();
    }

    private function pushProgramDuplicateExamples(array &$examples, int $exerciseProgramId, int $limit, string $mode): void
    {
        foreach ($this->signatureIndex($exerciseProgramId, $mode) as $signature => $pivotIds) {
            if (count($pivotIds) <= 1) {
                continue;
            }

            $this->pushExample($examples, [
                'exercise_program_id' => $exerciseProgramId,
                'match_mode' => $mode,
                'signature' => $signature,
                'pivot_ids' => $pivotIds,
            ], $limit);
        }
    }

    private function auditDirectIdentity(
        TrainingProgramSlotExercise $slotExercise,
        int $exerciseProgramId,
        string $slotSignature,
        string $slotLooseSignature,
        array &$summary,
        array &$examples,
        int $showLimit,
    ): void {
        $pivotId = (int) ($slotExercise->exercise_program_exercise_id ?? 0);

        if ($pivotId <= 0) {
            return;
        }

        $summary['direct_identity_present']++;

        $pivot = ExerciseProgramExercise::query()
            ->whereKey($pivotId)
            ->first(['id', 'exercise_program_id', 'exercise_id', 'sort', 'group', 'type']);

        if ($pivot === null) {
            $summary['direct_identity_missing_pivot']++;
            $this->pushExample($examples['direct_missing'], $this->exampleRow($slotExercise, reason: 'Direct pivot id no longer exists'), $showLimit);

            return;
        }

        if ((int) $pivot->exercise_program_id !== $exerciseProgramId) {
            $summary['direct_identity_program_mismatch']++;
            $this->pushExample($examples['direct_mismatch'], $this->exampleRow($slotExercise, reason: 'Direct pivot belongs to a different exercise program'), $showLimit);

            return;
        }

        $pivotSignature = $this->strictSignature(
            exerciseId: (int) $pivot->exercise_id,
            sort: (int) ($pivot->sort ?? 0),
            group: $pivot->group,
            type: (string) ($pivot->type ?? 'main'),
        );
        $pivotLooseSignature = $this->looseSignature(
            exerciseId: (int) $pivot->exercise_id,
            type: (string) ($pivot->type ?? 'main'),
        );

        if ($pivotLooseSignature !== $slotLooseSignature) {
            $summary['direct_identity_loose_signature_mismatch']++;
            $this->pushExample($examples['direct_mismatch'], $this->exampleRow($slotExercise, reason: 'Direct pivot exercise/type differs from materialized slot exercise'), $showLimit);

            return;
        }

        if ($pivotSignature !== $slotSignature) {
            $summary['direct_identity_strict_signature_mismatch']++;
        }

        $summary['direct_identity_valid']++;
    }

    private function strictSignature(int $exerciseId, int $sort, mixed $group, string $type): string
    {
        return implode(':', [
            $exerciseId,
            $sort,
            $group === null ? '' : (string) $group,
            $type !== '' ? $type : 'main',
        ]);
    }

    private function looseSignature(int $exerciseId, string $type): string
    {
        return implode(':', [
            $exerciseId,
            $type !== '' ? $type : 'main',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $examples
     * @param  array<string, mixed>  $example
     */
    private function pushExample(array &$examples, array $example, int $limit): void
    {
        if ($limit <= 0 || count($examples) >= $limit) {
            return;
        }

        $examples[] = $example;
    }

    /**
     * @param  array<int, int>  $matchingPivotIds
     * @return array<string, mixed>
     */
    private function exampleRow(TrainingProgramSlotExercise $slotExercise, string $reason, array $matchingPivotIds = []): array
    {
        return [
            'reason' => $reason,
            'slot_exercise_id' => $slotExercise->id,
            'slot_id' => $slotExercise->training_program_slot_id,
            'training_program_id' => $slotExercise->slot?->training_program_id,
            'exercise_program_id' => $slotExercise->slot?->trainingProgram?->exercise_program_id,
            'user_id' => $slotExercise->slot?->user_id,
            'exercise_id' => $slotExercise->exercise_id,
            'sort' => $slotExercise->sort,
            'group' => $slotExercise->group,
            'type' => $slotExercise->type,
            'matching_pivot_ids' => $matchingPivotIds,
        ];
    }

    /**
     * @param  array<string, int|bool>  $summary
     * @param  array<string, array<int, array<string, mixed>>>  $examples
     */
    private function renderReport(array $summary, array $examples): void
    {
        $this->info('Slot exercise identity audit');
        $this->line('Direct identity column exists: '.($summary['direct_identity_column_exists'] ? 'yes' : 'no'));
        $this->newLine();

        $this->table(['Metric', 'Count'], collect($summary)
            ->reject(fn (mixed $value, string $key): bool => $key === 'direct_identity_column_exists')
            ->map(fn (mixed $value, string $key): array => [$key, (string) $value])
            ->values()
            ->all());

        foreach ($examples as $section => $rows) {
            if ($rows === []) {
                continue;
            }

            $this->newLine();
            $this->warn(str_replace('_', ' ', ucfirst($section)).' examples');

            foreach ($rows as $row) {
                $this->line(json_encode($row, JSON_THROW_ON_ERROR));
            }
        }

        if ($summary['strict_missing_matches'] > 0 || $summary['strict_ambiguous_matches'] > 0) {
            $this->newLine();
            $this->warn('Some materialized slot exercises cannot be mapped uniquely by the current signature lookup.');
        } else {
            $this->newLine();
            $this->info('Every audited slot exercise maps uniquely by the current signature lookup.');
        }
    }
}
