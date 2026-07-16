<?php

namespace App\Console\Commands;

use App\Casts\ExercisePlanConfigCast;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Data\Training\Config\ExerciseOverrides;
use App\Data\Training\Config\ExercisePlanConfig;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Training\TrainingSessionRebuildService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RepairImportedProgramOverridesCommand extends Command
{
    protected $signature = 'training:repair-imported-program-overrides
        {sourceProgramId=303 : Original exercise program id to copy overrides from}
        {--training-program=* : Limit repair to one or more training program ids}
        {--from= : Rebuild open slots on or after this date after repair}
        {--dry-run : Report what would change without writing}
        {--skip-existing : Skip cloned programs that already have override rows}
        {--no-rebuild : Do not rebuild affected open slots after repair}';

    protected $description = 'One-time repair for imported calendar programs whose table-backed grid overrides were not copied from the original program';

    public function handle(TrainingSessionRebuildService $rebuildService): int
    {
        $sourceProgram = ExerciseProgram::withTrashed()
            ->with('exercises')
            ->find((int) $this->argument('sourceProgramId'));

        if (! $sourceProgram) {
            $this->error('Source program not found.');

            return self::FAILURE;
        }

        $sourceRows = $sourceProgram->planConfigOverrides()->count();

        if ($sourceRows === 0) {
            $this->warn("Source program {$sourceProgram->id} has no table-backed override rows to copy.");

            return self::SUCCESS;
        }

        $sourceSignature = $this->exerciseSignature($sourceProgram);
        $sourcePivotIds = $sourceProgram->exercises
            ->pluck('pivot.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        if ($sourceSignature === []) {
            $this->error('Source program has no exercises, so cloned pivots cannot be matched.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $skipExisting = (bool) $this->option('skip-existing');
        $rebuild = ! (bool) $this->option('no-rebuild');
        $fromDate = $this->option('from');
        $limitTrainingProgramIds = collect($this->option('training-program'))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $this->info(sprintf(
            '%s clones of exercise program %d (%s), source override rows: %d',
            $dryRun ? 'Scanning' : 'Repairing',
            $sourceProgram->id,
            $sourceProgram->name,
            $sourceRows,
        ));

        $matched = 0;
        $changed = 0;
        $skipped = 0;
        $rebuilt = 0;

        $query = TrainingProgram::query()
            ->with([
                'program.exercises' => fn ($relation) => $relation
                    ->orderByPivot('type')
                    ->orderByPivot('sort')
                    ->orderByPivot('id'),
            ])
            ->where('exercise_program_id', '!=', $sourceProgram->id)
            ->whereHas('program', fn ($query) => $query->where('name', $sourceProgram->name));

        if ($limitTrainingProgramIds !== []) {
            $query->whereIn('id', $limitTrainingProgramIds);
        }

        $query
            ->orderBy('id')
            ->chunkById(100, function (Collection $trainingPrograms) use (
                $sourceProgram,
                $sourceSignature,
                $sourcePivotIds,
                $sourceRows,
                $dryRun,
                $skipExisting,
                $rebuild,
                $fromDate,
                $rebuildService,
                &$matched,
                &$changed,
                &$skipped,
                &$rebuilt,
            ): void {
                foreach ($trainingPrograms as $trainingProgram) {
                    $targetProgram = $trainingProgram->program;

                    if (! $targetProgram instanceof ExerciseProgram) {
                        continue;
                    }

                    if ($this->exerciseSignature($targetProgram) !== $sourceSignature) {
                        continue;
                    }

                    $matched++;

                    $targetPivotIds = $targetProgram->exercises
                        ->pluck('pivot.id')
                        ->map(fn (mixed $id): int => (int) $id)
                        ->values()
                        ->all();
                    $pivotIdMap = array_combine($sourcePivotIds, $targetPivotIds);

                    if (! is_array($pivotIdMap)) {
                        $skipped++;
                        $this->warn("Skipping training program {$trainingProgram->id}: could not map exercise pivots.");

                        continue;
                    }

                    $existingRows = $targetProgram->planConfigOverrides()->count();

                    if ($existingRows > 0 && $skipExisting) {
                        $skipped++;
                        $this->line("Skipping training program {$trainingProgram->id} / exercise program {$targetProgram->id}: already has {$existingRows} override rows.");

                        continue;
                    }

                    $this->line(sprintf(
                        '%s training program %d / exercise program %d: %d existing rows, expanding from %d source rows',
                        $dryRun ? 'Would repair' : 'Repairing',
                        $trainingProgram->id,
                        $targetProgram->id,
                        $existingRows,
                        $sourceRows,
                    ));

                    if (! $dryRun) {
                        DB::transaction(function () use ($sourceProgram, $targetProgram, $trainingProgram, $pivotIdMap): void {
                            ExercisePlanConfigCast::forgetOverrideRowsFor($sourceProgram);
                            ExercisePlanConfigCast::forgetOverrideRowsFor($targetProgram);

                            $sourceConfig = $sourceProgram->fresh()->config;
                            $targetProgram = $targetProgram->fresh();
                            $targetConfig = $targetProgram->config;
                            $targetConfig->copyMappedExerciseOverridesFrom($sourceConfig, $pivotIdMap);
                            $this->expandCurrentOverridesToScheduledSessions($trainingProgram, $targetConfig);

                            foreach ($pivotIdMap as $targetPivotId) {
                                $overrides = $targetConfig->defaultExerciseOverrides((int) $targetPivotId);

                                if ($overrides->hasAnyGridOverrides()) {
                                    $overrides->baselineGridOverrides = $overrides->gridOverrides;
                                    $targetConfig->setDefaultExerciseOverrides((int) $targetPivotId, $overrides);
                                }
                            }

                            foreach ($targetConfig->allUserExerciseOverrides() as $userId => $overridesByExercise) {
                                foreach (array_keys($overridesByExercise) as $programExerciseId) {
                                    $overrides = $targetConfig->userExerciseOverrides((int) $userId, (int) $programExerciseId);

                                    if ($overrides->hasAnyGridOverrides()) {
                                        $overrides->baselineGridOverrides = $overrides->gridOverrides;
                                        $targetConfig->setUserExerciseOverrides((int) $userId, (int) $programExerciseId, $overrides);
                                    }
                                }
                            }

                            $targetProgram->config = $targetConfig;
                            $targetProgram->save();
                        });

                        if ($rebuild) {
                            $rebuildService->rebuildOpenSlotsForTrainingProgram($trainingProgram->id, is_string($fromDate) && $fromDate !== '' ? $fromDate : null);
                            $rebuilt++;
                        }
                    }

                    $changed++;
                }
            });

        $this->newLine();
        $this->info("Matched clones: {$matched}");
        $this->info(($dryRun ? 'Would repair' : 'Repaired').": {$changed}");
        $this->info("Skipped: {$skipped}");

        if (! $dryRun && $rebuild) {
            $this->info("Rebuilt training programs: {$rebuilt}");
        }

        return self::SUCCESS;
    }

    /** @return list<array{exercise_id: int, type: string, group: string, sort: int}> */
    private function exerciseSignature(ExerciseProgram $program): array
    {
        $program->loadMissing('exercises');

        return $program->exercises
            ->sortBy([
                fn ($a, $b): int => ((string) ($a->pivot->type ?? 'main')) <=> ((string) ($b->pivot->type ?? 'main')),
                fn ($a, $b): int => ((int) ($a->pivot->sort ?? 0)) <=> ((int) ($b->pivot->sort ?? 0)),
                fn ($a, $b): int => ((int) ($a->pivot->id ?? 0)) <=> ((int) ($b->pivot->id ?? 0)),
            ])
            ->values()
            ->map(fn ($exercise): array => [
                'exercise_id' => (int) $exercise->id,
                'type' => (string) ($exercise->pivot->type ?? 'main'),
                'group' => (string) ($exercise->pivot->group ?? ''),
                'sort' => (int) ($exercise->pivot->sort ?? 0),
            ])
            ->all();
    }

    private function expandCurrentOverridesToScheduledSessions(?TrainingProgram $trainingProgram, ExercisePlanConfig $config): void
    {
        if (! $trainingProgram instanceof TrainingProgram) {
            return;
        }

        $defaultContexts = $this->scheduledSessionContexts($trainingProgram->id);

        if ($defaultContexts !== []) {
            foreach (array_keys($config->exercises) as $programExerciseId) {
                $overrides = $config->defaultExerciseOverrides((int) $programExerciseId);
                $overrides->gridOverrides = $this->repeatFirstSessionPattern($overrides, $defaultContexts);
                $config->setDefaultExerciseOverrides((int) $programExerciseId, $overrides);
            }
        }

        foreach ($config->allUserExerciseOverrides() as $userId => $overridesByExercise) {
            $userContexts = $this->scheduledSessionContexts($trainingProgram->id, (int) $userId);

            if ($userContexts === []) {
                continue;
            }

            foreach (array_keys($overridesByExercise) as $programExerciseId) {
                $overrides = $config->userExerciseOverrides((int) $userId, (int) $programExerciseId);
                $overrides->gridOverrides = $this->repeatFirstSessionPattern($overrides, $userContexts);
                $config->setUserExerciseOverrides((int) $userId, (int) $programExerciseId, $overrides);
            }
        }
    }

    /** @param list<array{week: int, session: int}> $contexts */
    private function repeatFirstSessionPattern(ExerciseOverrides $overrides, array $contexts): array
    {
        $gridOverrides = $overrides->gridOverrides;
        $origin = $this->firstOverrideCoordinate($gridOverrides);

        if ($origin === null) {
            return $gridOverrides;
        }

        $expanded = ['sessions' => [], 'cells' => []];

        foreach ($contexts as $context) {
            foreach ($gridOverrides['sessions'] ?? [] as $entry) {
                if ((int) ($entry['week'] ?? 0) !== $origin['week'] || (int) ($entry['session'] ?? 0) !== $origin['session']) {
                    continue;
                }

                $expanded['sessions'][] = array_replace($entry, [
                    'week' => $context['week'],
                    'session' => $context['session'],
                ]);
            }

            foreach ($gridOverrides['cells'] ?? [] as $entry) {
                if ((int) ($entry['week'] ?? 0) !== $origin['week'] || (int) ($entry['session'] ?? 0) !== $origin['session']) {
                    continue;
                }

                $expanded['cells'][] = array_replace($entry, [
                    'week' => $context['week'],
                    'session' => $context['session'],
                ]);
            }
        }

        return EffectiveExerciseConfig::mergeGridOverrides($gridOverrides, $expanded);
    }

    /** @return array{week: int, session: int}|null */
    private function firstOverrideCoordinate(array $gridOverrides): ?array
    {
        $coordinates = [];

        foreach ($gridOverrides['sessions'] ?? [] as $entry) {
            $coordinates[] = [
                'week' => (int) ($entry['week'] ?? 0),
                'session' => (int) ($entry['session'] ?? 0),
            ];
        }

        foreach ($gridOverrides['cells'] ?? [] as $entry) {
            $coordinates[] = [
                'week' => (int) ($entry['week'] ?? 0),
                'session' => (int) ($entry['session'] ?? 0),
            ];
        }

        if ($coordinates === []) {
            return null;
        }

        usort($coordinates, fn (array $a, array $b): int => [$a['week'], $a['session']] <=> [$b['week'], $b['session']]);

        return $coordinates[0];
    }

    /** @return list<array{week: int, session: int}> */
    private function scheduledSessionContexts(int $trainingProgramId, ?int $userId = null): array
    {
        $slots = TrainingProgramSlot::query()
            ->where('training_program_id', $trainingProgramId)
            ->whereNull('cancelled_at')
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->orderBy('user_id')
            ->orderBy('datetime')
            ->orderBy('id')
            ->get(['id', 'user_id', 'datetime']);

        $contexts = [];

        foreach ($slots->groupBy('user_id') as $userSlots) {
            $weeks = $userSlots
                ->groupBy(fn (TrainingProgramSlot $slot): string => $slot->datetime->isoWeekYear().'-'.$slot->datetime->isoWeek())
                ->values();

            foreach ($weeks as $weekIndex => $weekSlots) {
                foreach ($weekSlots->values() as $sessionIndex => $slot) {
                    $contexts[$weekIndex.'-'.$sessionIndex] = [
                        'week' => (int) $weekIndex,
                        'session' => (int) $sessionIndex,
                    ];
                }
            }
        }

        return array_values($contexts);
    }
}
