<?php

namespace App\Console\Commands;

use App\Casts\ExercisePlanConfigCast;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Models\Exercise\ExercisePlanConfigOverride;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionRebuildService;
use App\Training\TrainingStateRevisionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class RepairLegacyGroupedOverrideCoordinatesCommand extends Command
{
    protected $signature = 'training:repair-legacy-grouped-overrides
        {trainingProgramId : Calendar training program id}
        {--user=* : Athlete ids to repair}
        {--group= : Repair every member plus shared overrides and every grouped exercise for this group}
        {--shared : Repair shared overrides inherited by athletes without their own values}
        {--program-exercise=* : Cloned program exercise pivot ids to repair}
        {--cutoff=2026-08-30 21:58:15 : Only rows last changed on or before this timestamp are eligible}
        {--updated-by= : Required user id for an applied repair audit}
        {--preserve-existing : Discard a misplaced legacy row when its canonical destination is already occupied}
        {--apply : Commit the coordinate remap}
        {--rebuild : Rebuild mutable slots for the selected athletes after applying}
        {--report= : JSON report/backup path; defaults below storage/app/reports}';

    protected $description = 'Safely remap legacy flattened fixed-group overrides into canonical group/session coordinates';

    public function handle(
        TrainingStateRevisionService $revisionService,
        TrainingSessionRebuildService $rebuildService,
    ): int {
        try {
            [$trainingProgram, $userIds, $programExerciseIds, $cutoff, $updatedBy, $shared, $groupWide] = $this->validatedOptions();
            $plan = $this->buildPlan($trainingProgram, $userIds, $programExerciseIds, $cutoff, $shared, $groupWide);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $path = $this->writeReport($trainingProgram, $cutoff, $plan);
        $this->renderPlan($plan, $path);

        if (! $this->option('apply')) {
            $this->info('Dry run only. Re-run with --apply and --updated-by to commit this exact remap.');

            return self::SUCCESS;
        }

        if ($plan === []) {
            $this->info('Nothing to repair.');

            return self::SUCCESS;
        }

        Auth::loginUsingId($updatedBy);

        DB::transaction(function () use ($trainingProgram, $cutoff, $plan, $updatedBy, $revisionService): void {
            $program = ExerciseProgram::query()->lockForUpdate()->findOrFail($trainingProgram->exercise_program_id);
            $rowIds = array_column($plan, 'id');
            $rows = ExercisePlanConfigOverride::query()
                ->whereIn('id', $rowIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($rows->count() !== count($rowIds)) {
                throw new InvalidArgumentException('One or more planned override rows disappeared; no changes were committed.');
            }

            foreach ($plan as $change) {
                $row = $rows->get($change['id']);

                if (! $row instanceof ExercisePlanConfigOverride
                    || (int) $row->week_index !== $change['from']['week']
                    || (int) $row->session_index !== $change['from']['session']
                    || ! $row->updated_at?->lte($cutoff)) {
                    throw new InvalidArgumentException("Override row {$change['id']} changed after the dry-run plan; no changes were committed.");
                }

                if ($change['operation'] === 'discard') {
                    $target = ExercisePlanConfigOverride::query()->lockForUpdate()->find($change['preserved_row_id']);

                    if (! $target instanceof ExercisePlanConfigOverride
                        || $this->coordinateKey($target) !== $this->coordinateKey($row, $change['to']['week'], $change['to']['session'])) {
                        throw new InvalidArgumentException("Preserved override row {$change['preserved_row_id']} changed after the dry-run plan; no changes were committed.");
                    }
                }
            }

            $batch = $revisionService->createBatch(
                owner: $program,
                action: 'repair_legacy_grouped_override_coordinates',
                domain: 'plan',
                context: [
                    'training_program_id' => (int) $trainingProgram->id,
                    'cutoff' => $cutoff->toDateTimeString(),
                    'row_count' => count($plan),
                ],
                source: 'system',
            );

            foreach ($plan as $change) {
                /** @var ExercisePlanConfigOverride $row */
                $row = $rows->get($change['id']);
                $before = $this->auditPayload($row);

                if ($change['operation'] === 'discard') {
                    $target = ExercisePlanConfigOverride::query()->findOrFail($change['preserved_row_id']);
                    $revisionService->recordStateChange(
                        batch: $batch,
                        subject: $row,
                        stateKey: 'override_coordinate',
                        beforeValue: 'legacy',
                        afterValue: 'canonical_existing',
                        beforePayload: $before,
                        afterPayload: [
                            'discarded_id' => (int) $row->id,
                            'preserved' => $this->auditPayload($target),
                        ],
                    );
                    $row->deleteQuietly();

                    continue;
                }

                $row->forceFill([
                    'week_index' => $change['to']['week'],
                    'session_index' => $change['to']['session'],
                    'updated_by' => $updatedBy,
                ])->saveQuietly();

                $revisionService->recordStateChange(
                    batch: $batch,
                    subject: $row,
                    stateKey: 'override_coordinate',
                    beforeValue: 'legacy',
                    afterValue: 'canonical',
                    beforePayload: $before,
                    afterPayload: $this->auditPayload($row),
                );
            }

            ExercisePlanConfigCast::forgetOverrideRowsFor($program);
        }, 5);

        if ($this->option('rebuild')) {
            if ($groupWide || $shared) {
                $rebuildService->rebuildOpenSlotsForTrainingProgram($trainingProgram->id);
            } else {
                foreach ($userIds as $userId) {
                    $rebuildService->rebuildOpenSlotsForTrainingProgramAthlete($trainingProgram->id, $userId);
                }
            }
        }

        $this->info(sprintf(
            'Applied %d coordinate changes%s. Backup/report: %s',
            count($plan),
            $this->option('rebuild') ? ' and rebuilt mutable slots' : ' without rebuilding slots',
            $path,
        ));

        return self::SUCCESS;
    }

    /** @return array{0: TrainingProgram, 1: list<int>, 2: list<int>, 3: Carbon, 4: ?int, 5: bool, 6: bool} */
    private function validatedOptions(): array
    {
        $trainingProgramId = (int) $this->argument('trainingProgramId');
        $trainingProgram = TrainingProgram::query()->with('program.exercises')->find($trainingProgramId);

        if (! $trainingProgram instanceof TrainingProgram || ! $trainingProgram->program instanceof ExerciseProgram) {
            throw new InvalidArgumentException("Training program {$trainingProgramId} was not found.");
        }

        $userIds = $this->positiveIds('user');
        $programExerciseIds = $this->positiveIds('program-exercise');
        $shared = (bool) $this->option('shared');
        $groupId = (int) $this->option('group');
        $groupWide = $groupId > 0;

        if ($groupWide) {
            $group = UserGroup::query()->with('members')->find($groupId);

            if (! $group instanceof UserGroup || (int) $trainingProgram->group_id !== $groupId) {
                throw new InvalidArgumentException("Group {$groupId} does not own training program {$trainingProgramId}.");
            }

            $userIds = collect($userIds)
                ->merge($group->members->modelKeys())
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $shared = true;

            if ($programExerciseIds === []) {
                $programExerciseIds = $trainingProgram->program->exercises
                    ->pluck('pivot.id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values()
                    ->all();
            }
        }

        if (($userIds === [] && ! $shared) || $programExerciseIds === []) {
            throw new InvalidArgumentException('At least one --user or --shared, and one --program-exercise, are required.');
        }

        $validExerciseIds = ExerciseProgramExercise::query()
            ->where('exercise_program_id', $trainingProgram->exercise_program_id)
            ->whereIn('id', $programExerciseIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if (count($validExerciseIds) !== count($programExerciseIds)) {
            throw new InvalidArgumentException('One or more --program-exercise ids do not belong to this training program.');
        }

        foreach ($groupWide ? [] : $userIds as $userId) {
            if (! TrainingProgramSlot::query()
                ->where('training_program_id', $trainingProgramId)
                ->where('user_id', $userId)
                ->exists()) {
                throw new InvalidArgumentException("Athlete {$userId} has no slots in training program {$trainingProgramId}.");
            }
        }

        try {
            $cutoff = Carbon::parse((string) $this->option('cutoff'));
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid --cutoff timestamp.');
        }

        $updatedBy = $this->option('updated-by') === null ? null : (int) $this->option('updated-by');

        if ($this->option('apply') && ($updatedBy === null || ! User::query()->whereKey($updatedBy)->exists())) {
            throw new InvalidArgumentException('--updated-by must identify an existing user when applying.');
        }

        if ($this->option('rebuild') && ! $this->option('apply')) {
            throw new InvalidArgumentException('--rebuild may only be used with --apply.');
        }

        return [$trainingProgram, $userIds, $programExerciseIds, $cutoff, $updatedBy, $shared, $groupWide];
    }

    /** @return list<int> */
    private function positiveIds(string $option): array
    {
        return collect($this->option($option))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function buildPlan(
        TrainingProgram $trainingProgram,
        array $userIds,
        array $programExerciseIds,
        Carbon $cutoff,
        bool $shared,
        bool $skipUngrouped,
    ): array {
        $program = $trainingProgram->program;
        $groupSizes = [];
        $subjects = $shared ? [null, ...$userIds] : $userIds;

        foreach ($subjects as $userId) {
            foreach ($programExerciseIds as $programExerciseId) {
                $exercise = $program->exercises->first(
                    fn ($exercise): bool => (int) $exercise->pivot->id === $programExerciseId,
                );
                $resolved = $program->config->resolveExercise($exercise->config, $programExerciseId, $userId);
                $preview = data_get($resolved->effectiveConfig, 'preview', []);
                $mode = SessionGroupingMode::normalizeMode((string) ($preview['groupingMode'] ?? ''));

                if ($mode !== SessionGroupingMode::Groups->value) {
                    if ($skipUngrouped) {
                        continue;
                    }

                    throw new InvalidArgumentException("Program exercise {$programExerciseId} is not fixed-session grouped for athlete {$userId}.");
                }

                $groupSizes[$userId ?? 'shared'][$programExerciseId] = SessionGroupingMode::normalizeGroupSize(
                    isset($preview['groupSize']) ? (int) $preview['groupSize'] : null,
                    $mode,
                );
            }
        }

        $rows = ExercisePlanConfigOverride::query()
            ->where('owner_type', $program->getMorphClass())
            ->where('owner_id', $program->id)
            ->where(function ($query) use ($shared, $userIds): void {
                if ($shared) {
                    $query->whereNull('user_id');
                }

                if ($userIds !== []) {
                    $shared ? $query->orWhereIn('user_id', $userIds) : $query->whereIn('user_id', $userIds);
                }
            })
            ->whereIn('program_exercise_id', $programExerciseIds)
            ->where('session_index', 0)
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('user_id')
            ->orderBy('program_exercise_id')
            ->orderBy('scope')
            ->orderBy('target')
            ->orderBy('week_index')
            ->orderBy('set_index')
            ->orderBy('setting_key')
            ->get();
        $selectedIds = $rows->pluck('id')->mapWithKeys(fn (mixed $id): array => [(int) $id => true])->all();
        $occupied = ExercisePlanConfigOverride::query()
            ->where('owner_type', $program->getMorphClass())
            ->where('owner_id', $program->id)
            ->where(function ($query) use ($shared, $userIds): void {
                if ($shared) {
                    $query->whereNull('user_id');
                }

                if ($userIds !== []) {
                    $shared ? $query->orWhereIn('user_id', $userIds) : $query->whereIn('user_id', $userIds);
                }
            })
            ->whereIn('program_exercise_id', $programExerciseIds)
            ->get()
            ->mapWithKeys(fn (ExercisePlanConfigOverride $row): array => [$this->coordinateKey($row) => (int) $row->id])
            ->all();
        $plannedKeys = [];
        $plan = [];

        foreach ($rows as $row) {
            $groupSize = $groupSizes[$row->user_id ?? 'shared'][$row->program_exercise_id] ?? null;

            if ($groupSize === null) {
                continue;
            }
            $newWeek = intdiv((int) $row->week_index, $groupSize);
            $newSession = (int) $row->week_index % $groupSize;

            if ($newWeek === (int) $row->week_index && $newSession === (int) $row->session_index) {
                continue;
            }

            $newKey = $this->coordinateKey($row, $newWeek, $newSession);
            $occupantId = $occupied[$newKey] ?? null;

            if ($occupantId !== null && ! isset($selectedIds[$occupantId])) {
                if (! $this->option('preserve-existing') && ! $skipUngrouped) {
                    throw new InvalidArgumentException("Coordinate collision: row {$row->id} would overwrite row {$occupantId} at {$newKey}.");
                }

                $plan[] = [
                    ...$this->planPayload($row, $newWeek, $newSession),
                    'operation' => 'discard',
                    'preserved_row_id' => $occupantId,
                ];

                continue;
            }

            if (isset($plannedKeys[$newKey]) && $plannedKeys[$newKey] !== (int) $row->id) {
                throw new InvalidArgumentException("Coordinate collision between rows {$plannedKeys[$newKey]} and {$row->id} at {$newKey}.");
            }

            $plannedKeys[$newKey] = (int) $row->id;
            $plan[] = [
                ...$this->planPayload($row, $newWeek, $newSession),
                'operation' => 'move',
            ];
        }

        return $plan;
    }

    /** @return array<string, mixed> */
    private function planPayload(ExercisePlanConfigOverride $row, int $newWeek, int $newSession): array
    {
        return [
            'id' => (int) $row->id,
            'user_id' => $row->user_id === null ? null : (int) $row->user_id,
            'program_exercise_id' => (int) $row->program_exercise_id,
            'scope' => (string) $row->scope,
            'target' => (string) $row->target,
            'set_index' => $row->set_index,
            'setting_key' => (string) $row->setting_key,
            'value' => $row->getDecodedValue(),
            'created_at' => $row->created_at?->toDateTimeString(),
            'updated_at' => $row->updated_at?->toDateTimeString(),
            'from' => ['week' => (int) $row->week_index, 'session' => (int) $row->session_index],
            'to' => ['week' => $newWeek, 'session' => $newSession],
        ];
    }

    private function coordinateKey(ExercisePlanConfigOverride $row, ?int $week = null, ?int $session = null): string
    {
        return implode('|', [
            $row->owner_type,
            $row->owner_id,
            $row->program_exercise_id,
            $row->user_id,
            $row->scope,
            $row->target,
            $week ?? $row->week_index,
            $session ?? $row->session_index,
            $row->set_index ?? 'null',
            $row->setting_key,
        ]);
    }

    /** @return array<string, mixed> */
    private function auditPayload(ExercisePlanConfigOverride $row): array
    {
        return ['id' => (int) $row->id, ...$row->toFlatArray(), 'updated_by' => $row->updated_by];
    }

    private function writeReport(TrainingProgram $trainingProgram, Carbon $cutoff, array $plan): string
    {
        $path = trim((string) $this->option('report')) ?: storage_path(
            'app/reports/legacy-grouped-coordinate-repair-'.$trainingProgram->id.'-'.now()->format('Ymd-His').'.json',
        );
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'generated_at' => now()->toIso8601String(),
            'mode' => $this->option('apply') ? 'apply-backup' : 'dry-run',
            'training_program_id' => (int) $trainingProgram->id,
            'exercise_program_id' => (int) $trainingProgram->exercise_program_id,
            'cutoff' => $cutoff->toDateTimeString(),
            'changes' => $plan,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $path;
    }

    private function renderPlan(array $plan, string $path): void
    {
        $this->table(
            ['Row', 'Action', 'Athlete', 'Exercise', 'Scope', 'Target', 'Set', 'Setting', 'Value', 'From', 'To'],
            collect($plan)->map(fn (array $row): array => [
                $row['id'], $row['operation'], $row['user_id'] ?? 'shared', $row['program_exercise_id'], $row['scope'], $row['target'],
                $row['set_index'] ?? '-', $row['setting_key'], is_scalar($row['value']) ? (string) $row['value'] : json_encode($row['value']),
                $row['from']['week'].':'.$row['from']['session'], $row['to']['week'].':'.$row['to']['session'],
            ])->all(),
        );
        $this->info(sprintf('Planned %d coordinate changes. Report/backup: %s', count($plan), $path));
    }
}
