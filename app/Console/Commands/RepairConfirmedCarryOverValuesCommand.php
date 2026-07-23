<?php

namespace App\Console\Commands;

use App\Casts\ExercisePlanConfigCast;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Training\TrainingSessionPlannedValueService;
use App\Training\TrainingValueSnapshotCodec;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RepairConfirmedCarryOverValuesCommand extends Command
{
    protected $signature = 'training:repair-confirmed-carry-over-values
        {--case=* : Limit to one or more repair case keys}
        {--updated-by= : User id to stamp on override rows; omitted keeps existing updated_by values on updates}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Repair confirmed athlete carry-over projections that were written by the old compacted-set bug';

    public function handle(
        TrainingSessionPlannedValueService $plannedValueService,
        TrainingValueSnapshotCodec $codec,
    ): int {
        $caseKeys = collect($this->option('case'))
            ->map(fn (mixed $key): string => (string) $key)
            ->filter()
            ->values()
            ->all();
        $dryRun = (bool) $this->option('dry-run');
        $updatedBy = $this->option('updated-by');
        $updatedBy = is_numeric($updatedBy) ? (int) $updatedBy : null;

        $cases = collect($this->repairCases())
            ->when($caseKeys !== [], fn ($cases) => $cases->whereIn('key', $caseKeys))
            ->values()
            ->all();

        if ($cases === []) {
            $this->error('No matching repair cases found.');

            return self::FAILURE;
        }

        $this->info(($dryRun ? 'Scanning' : 'Repairing').' confirmed carry-over projection issues...');

        $changedSlots = 0;
        $changedOverrides = 0;

        foreach ($cases as $case) {
            $this->newLine();
            $this->line("<info>{$case['label']}</info>");

            $program = TrainingProgram::with('program')->find($case['training_program_id']);

            if (! $program || ! $program->program instanceof ExerciseProgram) {
                $this->warn("Missing training program {$case['training_program_id']}; skipping.");

                continue;
            }

            if ((int) $program->exercise_program_id !== (int) $case['exercise_program_id']) {
                $this->warn(sprintf(
                    'Training program %d uses exercise program %d, expected %d; skipping.',
                    $program->id,
                    $program->exercise_program_id,
                    $case['exercise_program_id'],
                ));

                continue;
            }

            DB::transaction(function () use (
                $case,
                $program,
                $plannedValueService,
                $codec,
                $dryRun,
                $updatedBy,
                &$changedSlots,
                &$changedOverrides,
            ): void {
                foreach ($case['targets'] as $target) {
                    $slotExercise = $this->targetSlotExercise($case, $target);

                    if (! $slotExercise) {
                        $this->warn("  {$target['date']}: slot exercise not found; skipping.");

                        continue;
                    }

                    $submittedValues = $this->submittedValues($slotExercise, $target['values']);
                    $plannedChanges = $this->plannedChanges($slotExercise, $submittedValues, $codec);

                    if ($plannedChanges === []) {
                        $this->line("  {$target['date']}: materialized planned values already correct.");
                    } else {
                        foreach ($plannedChanges as $change) {
                            $this->line(sprintf(
                                '  %s: set %d %s %s -> %s',
                                $target['date'],
                                $change['set'],
                                $change['setting'],
                                $this->formatValue($change['before']),
                                $this->formatValue($change['after']),
                            ));
                        }

                        if (! $dryRun) {
                            $plannedValueService->saveExercisePlannedValues($slotExercise, $submittedValues, true);
                        }

                        $changedSlots++;
                    }

                    $overrideChanges = $this->repairOverrides($program->program, $case, $target, $dryRun, $updatedBy);
                    $changedOverrides += $overrideChanges;
                }

                if (! $dryRun) {
                    ExercisePlanConfigCast::forgetOverrideRowsFor($program->program);
                }
            });
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would update' : 'Updated')." slot/date groups: {$changedSlots}");
        $this->info(($dryRun ? 'Would update' : 'Updated')." override rows: {$changedOverrides}");

        return self::SUCCESS;
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     training_program_id: int,
     *     exercise_program_id: int,
     *     user_id: int,
     *     program_exercise_id: ?int,
     *     targets: list<array{
     *         date: string,
     *         week_index: int,
     *         session_index: int,
     *         values: array<string, list<mixed>>
     *     }>
     * }>
     */
    private function repairCases(): array
    {
        return [
            [
                'key' => 'justine-row-bent-over',
                'label' => 'Justine Sauer / I - Row Bent Over / training program 97',
                'training_program_id' => 97,
                'exercise_program_id' => 228,
                'user_id' => 49,
                'program_exercise_id' => 903,
                'targets' => [
                    [
                        'date' => '2026-07-22',
                        'week_index' => 3,
                        'session_index' => 0,
                        'values' => [
                            'reps' => ['10', '10', '8', '0'],
                            'weight' => [14, 14, 14, 0],
                        ],
                    ],
                    [
                        'date' => '2026-07-29',
                        'week_index' => 4,
                        'session_index' => 0,
                        'values' => [
                            'reps' => ['10', '10', '8'],
                            'weight' => [14, 14, 14],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'wiederin-step-up',
                'label' => 'Christoph Wiederin / C1 - Step Up / training program 113',
                'training_program_id' => 113,
                'exercise_program_id' => 249,
                'user_id' => 53,
                'program_exercise_id' => 1111,
                'targets' => collect([
                    ['2026-07-28', 4],
                    ['2026-08-04', 5],
                    ['2026-08-11', 6],
                    ['2026-08-18', 7],
                    ['2026-08-25', 8],
                    ['2026-09-01', 9],
                ])->map(fn (array $target): array => [
                    'date' => $target[0],
                    'week_index' => $target[1],
                    'session_index' => 0,
                    'values' => [
                        'weight' => [10, 15, 15],
                    ],
                ])->all(),
            ],
            [
                'key' => 'beatriz-ph1-b',
                'label' => 'Beatriz Caspar / Bea PH1 B / training program 126',
                'training_program_id' => 126,
                'exercise_program_id' => 269,
                'user_id' => 55,
                'program_exercise_id' => null,
                'targets' => [
                    ...$this->targetsForExercise(1279, [
                        ['2026-07-23', 1, ['reps' => ['10', '10', '8'], 'weight' => [10, 10, 8]]],
                        ['2026-07-30', 2, ['reps' => ['10', '10', '8'], 'weight' => [10, 10, 8]]],
                        ['2026-08-06', 3, ['reps' => ['10', '10', '8'], 'weight' => [10, 10, 8]]],
                        ['2026-08-13', 4, ['reps' => ['10', '10', '8'], 'weight' => [10, 10, 8]]],
                    ]),
                    ...$this->targetsForExercise(1282, [
                        ['2026-07-23', 1, ['reps' => ['10', '10', '8'], 'weight' => [12.5, 12.5, 15]]],
                        ['2026-07-30', 2, ['reps' => ['10', '10', '8'], 'weight' => [12.5, 12.5, 15]]],
                        ['2026-08-06', 3, ['reps' => ['10', '10', '8'], 'weight' => [12.5, 12.5, 15]]],
                        ['2026-08-13', 4, ['reps' => ['10', '10', '8'], 'weight' => [12.5, 12.5, 15]]],
                    ]),
                    ...$this->targetsForExercise(1281, [
                        ['2026-07-23', 1, ['reps' => ['8', '10', '8']]],
                        ['2026-07-30', 2, ['reps' => ['8', '10', '8']]],
                        ['2026-08-06', 3, ['reps' => ['8', '10', '8']]],
                        ['2026-08-13', 4, ['reps' => ['8', '10', '8']]],
                    ]),
                ],
            ],
            [
                'key' => 'caroline-week-5-8-a',
                'label' => 'Caroline Bernasconi / Beginner Woman 2x Week 5-8 A / training program 172',
                'training_program_id' => 172,
                'exercise_program_id' => 336,
                'user_id' => 45,
                'program_exercise_id' => null,
                'targets' => [
                    ...$this->targetsForExercise(1821, [
                        ['2026-07-27', 1, ['weight' => [10, 12, 14]]],
                        ['2026-08-03', 2, ['weight' => [10, 12, 14]]],
                        ['2026-08-10', 3, ['weight' => [10, 12, 14]]],
                        ['2026-08-17', 4, ['weight' => [10, 12, 14]]],
                    ]),
                    ...$this->targetsForExercise(1820, [
                        ['2026-07-27', 1, ['weight' => [23, 25, 27]]],
                        ['2026-08-03', 2, ['weight' => [23, 25, 27]]],
                        ['2026-08-10', 3, ['weight' => [23, 25, 27]]],
                        ['2026-08-17', 4, ['weight' => [23, 25, 27]]],
                    ]),
                ],
            ],
            [
                'key' => 'caroline-week-5-8-b',
                'label' => 'Caroline Bernasconi / Beginner Woman 2x Week 5-8 B / training program 173',
                'training_program_id' => 173,
                'exercise_program_id' => 337,
                'user_id' => 45,
                'program_exercise_id' => null,
                'targets' => [
                    ...$this->targetsForExercise(1831, [
                        ['2026-07-29', 1, ['weight' => [10, 12, 14]]],
                        ['2026-08-05', 2, ['weight' => [10, 12, 14]]],
                        ['2026-08-12', 3, ['weight' => [10, 12, 14]]],
                        ['2026-08-19', 4, ['weight' => [10, 12, 14]]],
                    ]),
                    ...$this->targetsForExercise(1830, [
                        ['2026-07-29', 1, ['weight' => [23, 25, 27]]],
                        ['2026-08-05', 2, ['weight' => [23, 25, 27]]],
                        ['2026-08-12', 3, ['weight' => [23, 25, 27]]],
                        ['2026-08-19', 4, ['weight' => [23, 25, 27]]],
                    ]),
                ],
            ],
        ];
    }

    /**
     * @param list<array{0: string, 1: int, 2: array<string, list<mixed>>}> $targets
     * @return list<array{program_exercise_id: int, date: string, week_index: int, session_index: int, values: array<string, list<mixed>>}>
     */
    private function targetsForExercise(int $programExerciseId, array $targets): array
    {
        return array_map(fn (array $target): array => [
            'program_exercise_id' => $programExerciseId,
            'date' => $target[0],
            'week_index' => $target[1],
            'session_index' => 0,
            'values' => $target[2],
        ], $targets);
    }

    private function targetSlotExercise(array $case, array $target): ?TrainingProgramSlotExercise
    {
        return TrainingProgramSlotExercise::query()
            ->where('exercise_program_exercise_id', $target['program_exercise_id'] ?? $case['program_exercise_id'])
            ->whereHas('slot', fn ($query) => $query
                ->where('training_program_id', $case['training_program_id'])
                ->where('user_id', $case['user_id'])
                ->whereDate('scheduled_date', Carbon::parse($target['date'])->toDateString()))
            ->with(['slot', 'sets.values'])
            ->first();
    }

    /** @return array<int, array<string, mixed>> */
    private function submittedValues(TrainingProgramSlotExercise $slotExercise, array $valuesBySetting): array
    {
        $submitted = [];

        foreach ($slotExercise->sets->sortBy('set_number')->values() as $setIndex => $set) {
            foreach ($valuesBySetting as $setting => $values) {
                if (! array_key_exists($setIndex, $values)) {
                    continue;
                }

                $submitted[$set->id][$setting] = $values[$setIndex];
            }
        }

        return $submitted;
    }

    /** @return list<array{set: int, setting: string, before: mixed, after: mixed}> */
    private function plannedChanges(TrainingProgramSlotExercise $slotExercise, array $submittedValues, TrainingValueSnapshotCodec $codec): array
    {
        $changes = [];

        foreach ($slotExercise->sets as $set) {
            foreach ($set->values as $valueRow) {
                if (! array_key_exists($set->id, $submittedValues)
                    || ! array_key_exists($valueRow->setting_key, $submittedValues[$set->id])) {
                    continue;
                }

                $current = $codec->extractPlannedValue($valueRow);
                $target = $submittedValues[$set->id][$valueRow->setting_key];

                if ((string) $current === (string) $target) {
                    continue;
                }

                $changes[] = [
                    'set' => (int) $set->set_number,
                    'setting' => $valueRow->setting_key,
                    'before' => $current,
                    'after' => $target,
                ];
            }
        }

        return $changes;
    }

    private function repairOverrides(ExerciseProgram $program, array $case, array $target, bool $dryRun, ?int $updatedBy): int
    {
        $changed = 0;

        foreach ($target['values'] as $setting => $values) {
            foreach ($values as $setIndex => $value) {
                $attributes = [
                    'owner_type' => ExerciseProgram::class,
                    'owner_id' => $program->id,
                    'program_exercise_id' => $target['program_exercise_id'] ?? $case['program_exercise_id'],
                    'user_id' => $case['user_id'],
                    'scope' => 'current',
                    'target' => 'cell',
                    'week_index' => $target['week_index'],
                    'session_index' => $target['session_index'],
                    'set_index' => $setIndex,
                    'setting_key' => $setting,
                ];
                $encoded = json_encode($value);
                $existing = DB::table('exercise_plan_config_overrides')->where($attributes)->first();

                if ($existing && (string) $existing->value === (string) $encoded) {
                    continue;
                }

                $this->line(sprintf(
                    '  %s: override week %d session %d set %d %s %s -> %s',
                    $target['date'],
                    $target['week_index'],
                    $target['session_index'],
                    $setIndex + 1,
                    $setting,
                    $existing ? (string) $existing->value : 'missing',
                    (string) $encoded,
                ));

                if (! $dryRun) {
                    $now = now();

                    if ($existing) {
                        $updates = [
                            'value' => $encoded,
                            'updated_at' => $now,
                        ];

                        if ($updatedBy !== null) {
                            $updates['updated_by'] = $updatedBy;
                        }

                        DB::table('exercise_plan_config_overrides')->where('id', $existing->id)->update($updates);
                    } else {
                        DB::table('exercise_plan_config_overrides')->insert([
                            ...$attributes,
                            'value' => $encoded,
                            'created_by' => $updatedBy,
                            'updated_by' => $updatedBy,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                $changed++;
            }
        }

        return $changed;
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
        }

        if (is_array($value)) {
            return json_encode($value) ?: '[]';
        }

        return (string) $value;
    }
}
