<?php

namespace App\Console\Commands;

use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingPlanValueRevision;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Support\Training\ApplyPerScope;
use App\Support\Training\EffectiveSlotExerciseConfigResolver;
use App\Training\Planning\ExerciseSessionCoordinateResolver;
use App\Training\TrainingSessionCompiler;
use App\Training\TrainingValueSnapshotCodec;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class AuditHistoricalCarryOverCommand extends Command
{
    private const CARRIED_FIELDS = ['weight', 'reps', 'rest', 'tempo'];

    protected $signature = 'training:audit-historical-carry-over
        {--since= : Inclusive source athlete-actual timestamp}
        {--until= : Inclusive source athlete-actual timestamp, defaults to now}
        {--report= : CSV report path; defaults below storage/app/reports}';

    protected $description = 'Read-only audit of completed plans that should have inherited values from the immediately preceding completed session';

    public function handle(
        TrainingValueSnapshotCodec $codec,
        TrainingSessionCompiler $compiler,
        ExerciseSessionCoordinateResolver $coordinateResolver,
        EffectiveSlotExerciseConfigResolver $configResolver,
    ): int {
        try {
            [$since, $until] = $this->window();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $groups = $this->sourceGroups($since, $until);
        $rows = [];

        foreach ($groups as $group) {
            $timeline = $this->completedTimeline($group);

            foreach ($timeline->slice(1)->values() as $offset => $target) {
                $source = $timeline[$offset];

                if (! $this->sameBlock($source, $target, $compiler)) {
                    continue;
                }

                $config = $this->effectiveConfig($source, $configResolver);

                if (data_get($config, 'weight.mode', 'manual') !== 'manual'
                    || data_get($config, 'weight.carryOverAthleteValues', true) === false) {
                    continue;
                }

                $position = $this->position($target, $config, $compiler, $coordinateResolver);
                $sourceValues = $this->sourceValues($source, $codec);
                $targetSets = $target->sets->sortBy('set_number')->values();

                foreach ($targetSets as $setIndex => $set) {
                    foreach ($sourceValues as $field => $fieldValues) {
                        $targetValue = $set->values->firstWhere('setting_key', $field);
                        $sourceEntry = $fieldValues[$setIndex] ?? $this->lastEntry($fieldValues);

                        if (! $targetValue instanceof TrainingProgramSlotSetValue
                            || ! is_array($sourceEntry)
                            || ! $this->isRecordedTargetAfterSource($targetValue, $sourceEntry['recorded_at'])
                            || $sourceEntry['recorded_at']->lt($since)
                            || $sourceEntry['recorded_at']->gt($until)) {
                            continue;
                        }

                        $revisionSet = ApplyPerScope::normalize(data_get($config, $field.'.applyPer')) === ApplyPerScope::SESSION
                            ? null
                            : (int) $setIndex;

                        if ($this->laterCoachPlanExists($source, $target, $field, $position, $revisionSet, $sourceEntry['recorded_at'])) {
                            continue;
                        }

                        $planned = $codec->extractPlannedValue($targetValue);

                        if ($this->equivalent($planned, $sourceEntry['value'])) {
                            continue;
                        }

                        $rows[] = $this->reportRow(
                            source: $source,
                            target: $target,
                            targetValue: $targetValue,
                            field: $field,
                            setIndex: (int) $setIndex,
                            planned: $planned,
                            expected: $sourceEntry['value'],
                            actual: $codec->extractActualValue($targetValue),
                            compiler: $compiler,
                        );
                    }
                }
            }
        }

        usort($rows, fn (array $a, array $b): int => [
            $a['training_program_id'], $a['user_id'], $a['target_date'], $a['program_exercise_id'], $a['set'], $a['field'],
        ] <=> [
            $b['training_program_id'], $b['user_id'], $b['target_date'], $b['program_exercise_id'], $b['set'], $b['field'],
        ]);

        $path = $this->writeReport($rows, $since, $until);
        $this->renderSummary($rows, $groups->count(), $path);

        return self::SUCCESS;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function window(): array
    {
        $sinceValue = trim((string) $this->option('since'));
        $untilValue = trim((string) $this->option('until'));

        if ($sinceValue === '') {
            throw new InvalidArgumentException('Refusing to scan without --since.');
        }

        $since = Carbon::parse($sinceValue)->startOfDay();
        $until = $untilValue === '' ? now() : Carbon::parse($untilValue)->endOfDay();

        if ($since->gt($until)) {
            throw new InvalidArgumentException('--since must not be later than --until.');
        }

        return [$since, $until];
    }

    /** @return Collection<int, array{training_program_id: int, user_id: int, program_exercise_id: int}> */
    private function sourceGroups(Carbon $since, Carbon $until): Collection
    {
        return TrainingProgramSlotExercise::query()
            ->whereNotNull('exercise_program_exercise_id')
            ->where('status', TrainingProgramSlotExerciseStatusEnum::Completed)
            ->whereHas('slot', fn ($query) => $query
                ->where('status', TrainingProgramSlotStatusEnum::Completed)
                ->whereNull('cancelled_at'))
            ->whereHas('sets.values', fn ($query) => $query
                ->whereIn('setting_key', self::CARRIED_FIELDS)
                ->where('actual_is_explicit', true)
                ->whereNotNull('actual_value_type')
                ->whereBetween('actual_recorded_at', [$since, $until]))
            ->with('slot:id,training_program_id,user_id')
            ->get(['id', 'training_program_slot_id', 'exercise_program_exercise_id'])
            ->map(fn (TrainingProgramSlotExercise $exercise): array => [
                'training_program_id' => (int) $exercise->slot->training_program_id,
                'user_id' => (int) $exercise->slot->user_id,
                'program_exercise_id' => (int) $exercise->exercise_program_exercise_id,
            ])
            ->unique(fn (array $row): string => implode(':', $row))
            ->values();
    }

    /** @param array{training_program_id: int, user_id: int, program_exercise_id: int} $group */
    private function completedTimeline(array $group): Collection
    {
        return TrainingProgramSlotExercise::query()
            ->where('exercise_program_exercise_id', $group['program_exercise_id'])
            ->where('status', TrainingProgramSlotExerciseStatusEnum::Completed)
            ->whereHas('slot', fn ($query) => $query
                ->where('training_program_id', $group['training_program_id'])
                ->where('user_id', $group['user_id'])
                ->where('status', TrainingProgramSlotStatusEnum::Completed)
                ->whereNull('cancelled_at'))
            ->with(['slot.trainingProgram.program', 'exercise:id,name,config', 'settingSnapshot', 'sets.values'])
            ->get()
            ->sortBy(fn (TrainingProgramSlotExercise $exercise): string => sprintf(
                '%s:%010d',
                $exercise->slot->datetime->format('Y-m-d H:i:s'),
                $exercise->slot->id,
            ))
            ->values();
    }

    private function sameBlock(
        TrainingProgramSlotExercise $source,
        TrainingProgramSlotExercise $target,
        TrainingSessionCompiler $compiler,
    ): bool {
        return $compiler->categoryBlockForSlot($source->slot)?->id
            === $compiler->categoryBlockForSlot($target->slot)?->id;
    }

    /** @return array<string, mixed> */
    private function effectiveConfig(
        TrainingProgramSlotExercise $source,
        EffectiveSlotExerciseConfigResolver $fallback,
    ): array {
        $program = $source->slot?->trainingProgram?->program;
        $programConfig = $program?->config;

        if ($program === null || $source->exercise === null || ! is_object($programConfig) || ! method_exists($programConfig, 'resolveExercise')) {
            return $fallback->resolve($source);
        }

        return $programConfig->resolveExercise(
            $source->exercise->config,
            (int) $source->exercise_program_exercise_id,
            (int) $source->slot->user_id,
        )->effectiveConfig;
    }

    /** @return array<string, array<int, array{value: mixed, recorded_at: Carbon}>> */
    private function sourceValues(TrainingProgramSlotExercise $source, TrainingValueSnapshotCodec $codec): array
    {
        $values = [];

        foreach ($source->sets->sortBy('set_number')->values() as $setIndex => $set) {
            foreach (self::CARRIED_FIELDS as $field) {
                $row = $set->values->firstWhere('setting_key', $field);

                if (! $row instanceof TrainingProgramSlotSetValue
                    || ! $row->actual_is_explicit
                    || $row->actual_value_type === null
                    || ! $row->actual_recorded_at instanceof Carbon) {
                    continue;
                }

                $value = $codec->extractActualValue($row);

                if ($value !== null && $value !== '') {
                    $values[$field][(int) $setIndex] = ['value' => $value, 'recorded_at' => $row->actual_recorded_at];
                }
            }
        }

        return $values;
    }

    private function lastEntry(array $entries): ?array
    {
        if ($entries === []) {
            return null;
        }

        ksort($entries);
        $entry = end($entries);

        return is_array($entry) ? $entry : null;
    }

    private function isRecordedTargetAfterSource(TrainingProgramSlotSetValue $target, Carbon $sourceRecordedAt): bool
    {
        return $target->actual_is_explicit
            && $target->actual_value_type !== null
            && $target->actual_recorded_at instanceof Carbon
            && $target->actual_recorded_at->gt($sourceRecordedAt);
    }

    /** @return array{week: int, session: int, usesChronologicalSessions: bool, usesGroupedSlotIndex: bool} */
    private function position(
        TrainingProgramSlotExercise $target,
        array $config,
        TrainingSessionCompiler $compiler,
        ExerciseSessionCoordinateResolver $resolver,
    ): array {
        $context = $compiler->sessionContextForSlot($target->slot);

        return $resolver->resolve(
            effectiveConfig: $config,
            calendarWeekIndex: $context['weekIndex'],
            calendarSessionIndex: $context['sessionIndex'],
            slotIndex: $context['slotIndex'],
            useSlotIndexForGroupedSessions: true,
        );
    }

    private function laterCoachPlanExists(
        TrainingProgramSlotExercise $source,
        TrainingProgramSlotExercise $target,
        string $field,
        array $position,
        ?int $setIndex,
        Carbon $sourceRecordedAt,
    ): bool {
        $exerciseProgramId = (int) ($source->slot?->trainingProgram?->exercise_program_id ?? 0);

        return TrainingPlanValueRevision::query()
            ->where('program_exercise_id', $source->exercise_program_exercise_id)
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $source->slot->user_id))
            ->where('setting_key', $field)
            ->where('week_index', $position['week'])
            ->where('session_index', $position['session'])
            ->when($setIndex === null, fn ($query) => $query->whereNull('set_index'), fn ($query) => $query->where('set_index', $setIndex))
            ->whereIn('source', ['coach', 'admin'])
            ->where('created_at', '>', $sourceRecordedAt)
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('owner_type', ExerciseProgram::class)->where('owner_id', $exerciseProgramId))
                ->orWhere(fn ($query) => $query->where('owner_type', TrainingProgramSlotExercise::class)->where('owner_id', $target->id)))
            ->exists();
    }

    private function equivalent(mixed $first, mixed $second): bool
    {
        return is_numeric($first) && is_numeric($second)
            ? (float) $first === (float) $second
            : $first === $second;
    }

    /** @return array<string, mixed> */
    private function reportRow(
        TrainingProgramSlotExercise $source,
        TrainingProgramSlotExercise $target,
        TrainingProgramSlotSetValue $targetValue,
        string $field,
        int $setIndex,
        mixed $planned,
        mixed $expected,
        mixed $actual,
        TrainingSessionCompiler $compiler,
    ): array {
        $program = $target->slot->trainingProgram;
        $blockId = $compiler->categoryBlockForSlot($target->slot)?->id;
        $query = [
            'preset' => 'thisMonth', 'groupFilter' => 'all', 'group' => $program->group_id,
            'user' => $target->slot->user_id, 'planCategory' => $program->program->exercise_category_id,
            'planBlock' => $blockId ?? 'ungrouped', 'planProgram' => $program->id, 'view' => 'plan',
        ];

        return [
            'value_row_id' => $targetValue->id,
            'training_program_id' => $program->id,
            'group_id' => $program->group_id,
            'user_id' => $target->slot->user_id,
            'plan_category_id' => $program->program->exercise_category_id,
            'plan_block_id' => $blockId,
            'program_exercise_id' => $target->exercise_program_exercise_id,
            'exercise' => $target->exercise?->name,
            'source_slot_id' => $source->slot->id,
            'source_date' => $source->slot->datetime->toDateString(),
            'target_slot_id' => $target->slot->id,
            'target_date' => $target->slot->datetime->toDateString(),
            'set' => $setIndex + 1,
            'field' => $field,
            'planned' => $planned,
            'expected' => $expected,
            'actual' => $actual,
            'link' => 'https://app.arssporttraining.ch/admin/calendar?'.http_build_query($query),
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private function writeReport(array $rows, Carbon $since, Carbon $until): string
    {
        $path = trim((string) $this->option('report')) ?: storage_path('app/reports/historical-carry-over-audit-'.now()->format('Ymd-His').'.csv');
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new InvalidArgumentException("Could not create report {$path}.");
        }

        $headers = ['scan_since', 'scan_until', 'value_row_id', 'training_program_id', 'group_id', 'user_id', 'plan_category_id', 'plan_block_id', 'program_exercise_id', 'exercise', 'source_slot_id', 'source_date', 'target_slot_id', 'target_date', 'set', 'field', 'planned', 'expected', 'actual', 'link'];
        fputcsv($handle, $headers, ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, [$since->toDateTimeString(), $until->toDateTimeString(), ...array_map(fn (string $key): mixed => $row[$key] ?? null, array_slice($headers, 2))], ',', '"', '');
        }

        fclose($handle);

        return $path;
    }

    /** @param list<array<string, mixed>> $rows */
    private function renderSummary(array $rows, int $groupCount, string $path): void
    {
        $groups = collect($rows)->groupBy(fn (array $row): string => implode(':', [$row['training_program_id'], $row['user_id'], $row['plan_block_id'], $row['program_exercise_id']]));
        $this->table(['Program', 'Athlete', 'Block', 'Exercise', 'Cells', 'Target sessions', 'Review'], $groups->map(function ($rows): array {
            $first = $rows->first();

            return [$first['training_program_id'], $first['user_id'], $first['plan_block_id'] ?? 'ungrouped', $first['exercise'], $rows->count(), $rows->pluck('target_slot_id')->unique()->count(), $first['link']];
        })->values()->all());
        $this->info(sprintf('Found %d historical planned cells across %d completed target sessions while scanning %d exercise/athlete groups.', count($rows), collect($rows)->pluck('target_slot_id')->unique()->count(), $groupCount));
        $this->line('Read-only CSV report: '.$path);
    }
}
