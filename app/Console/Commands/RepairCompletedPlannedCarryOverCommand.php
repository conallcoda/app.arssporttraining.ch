<?php

namespace App\Console\Commands;

use App\Models\Training\TrainingPlanValueRevision;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Training\TrainingRevisionBatch;
use App\Training\TrainingSessionPlannedValueService;
use App\Training\TrainingValueSnapshotCodec;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RepairCompletedPlannedCarryOverCommand extends Command
{
    private const CASE_KEY = 'program-213-user-63-romanian-deadlift-week-3';

    private const PROGRAM_EXERCISE_ID = 2252;

    private const USER_ID = 63;

    private const SOURCE_SLOT_ID = 2442;

    private const TARGET_SLOT_ID = 2441;

    /** @var array<int, int> */
    private const EXPECTED_PLAN = [0 => 80, 1 => 85, 2 => 90];

    /** @var array<int, int> */
    private const EXPECTED_ACTUAL = [0 => 90, 1 => 94, 2 => 94];

    protected $signature = 'training:repair-completed-planned-carry-over
        {--case= : Required confirmed repair case key}
        {--updated-by= : Required user id for the correction audit when writing}
        {--dry-run : Validate and report without writing}';

    protected $description = 'Correct the three confirmed week-3 planned weight snapshots without changing actuals or completion state';

    public function handle(
        TrainingSessionPlannedValueService $plannedValueService,
        TrainingValueSnapshotCodec $codec,
    ): int {
        if ($this->option('case') !== self::CASE_KEY) {
            $this->error('Refusing to run: pass --case='.self::CASE_KEY.'.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updatedBy = filter_var($this->option('updated-by'), FILTER_VALIDATE_INT);

        if (! $dryRun && ($updatedBy === false || $updatedBy <= 0)) {
            $this->error('Refusing to write without a valid --updated-by user id.');

            return self::FAILURE;
        }

        try {
            [$source, $target] = $this->validateCase($codec);
        } catch (Throwable $exception) {
            $this->error('Guard failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->renderReport($target, $codec);

        if ($dryRun) {
            $this->info('Dry run complete; no data changed.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($source, $target, $plannedValueService, $codec, $updatedBy): void {
                $batch = TrainingRevisionBatch::query()->create([
                    'owner_type' => TrainingProgramSlotExercise::class,
                    'owner_id' => $target->id,
                    'domain' => 'plan',
                    'action' => 'correct_completed_plan',
                    'changed_by' => (int) $updatedBy,
                    'source' => 'system',
                    'reason' => 'Exact confirmed case '.self::CASE_KEY.'; restore the plan that should have carried from completed source slot 2442. Actual values and completion state are unchanged.',
                ]);

                $targetRows = $this->weightRows($target);

                foreach (self::EXPECTED_PLAN as $setIndex => $plannedWeight) {
                    $row = $targetRows[$setIndex];
                    $before = $codec->extractPlannedValue($row);
                    $actualBefore = $this->actualSnapshot($row);
                    $attributes = $plannedValueService->buildPlannedSnapshotAttributes($target, $row, $plannedWeight);

                    $row->forceFill($attributes)->save();
                    $this->guard($this->actualSnapshot($row->fresh()) === $actualBefore, "actual value changed for row {$row->id}");

                    TrainingPlanValueRevision::query()->create([
                        'batch_id' => $batch->id,
                        'owner_type' => TrainingProgramSlotExercise::class,
                        'owner_id' => $target->id,
                        'program_exercise_id' => self::PROGRAM_EXERCISE_ID,
                        'user_id' => self::USER_ID,
                        'setting_key' => 'weight',
                        'week_index' => 2,
                        'session_index' => 0,
                        'set_index' => $setIndex,
                        'changed_by' => (int) $updatedBy,
                        'source' => 'system',
                        'before_value_type' => 'decimal',
                        'before_decimal_value' => $before,
                        'after_value_type' => 'decimal',
                        'after_decimal_value' => $plannedWeight,
                        'unit' => 'kg',
                    ]);
                }

                $this->assertUnchangedSessionState($source, $target, $codec);
            }, 5);
        } catch (Throwable $exception) {
            $this->error('Correction rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Correction complete and audited. Three planned cells changed; no actual or session-state fields changed.');
        $this->line('Review: '.$this->calendarUrl());

        return self::SUCCESS;
    }

    /** @return array{0: TrainingProgramSlotExercise, 1: TrainingProgramSlotExercise} */
    private function validateCase(TrainingValueSnapshotCodec $codec): array
    {
        $sourceSlot = TrainingProgramSlot::query()->with('exercises.sets.values')->find(self::SOURCE_SLOT_ID);
        $targetSlot = TrainingProgramSlot::query()->with('exercises.sets.values')->find(self::TARGET_SLOT_ID);

        $this->guard(
            $sourceSlot?->training_program_id === 213
                && $sourceSlot->user_id === self::USER_ID
                && $sourceSlot->datetime?->toDateString() === '2026-08-18'
                && $sourceSlot->status === TrainingProgramSlotStatusEnum::Completed,
            'week-1 source session identity or state changed',
        );
        $this->guard(
            $targetSlot?->training_program_id === 213
                && $targetSlot->user_id === self::USER_ID
                && $targetSlot->datetime?->toDateString() === '2026-08-25'
                && $targetSlot->status === TrainingProgramSlotStatusEnum::Completed,
            'week-3 target session identity or state changed',
        );

        $source = $sourceSlot->exercises->firstWhere('exercise_program_exercise_id', self::PROGRAM_EXERCISE_ID);
        $target = $targetSlot->exercises->firstWhere('exercise_program_exercise_id', self::PROGRAM_EXERCISE_ID);
        $this->guard($source instanceof TrainingProgramSlotExercise, 'week-1 source exercise is missing');
        $this->guard($target instanceof TrainingProgramSlotExercise, 'week-3 target exercise is missing');

        $this->guard($this->plannedValues($source, $codec) === [80.0, 85.0, 90.0], 'week-1 source plan is no longer 80 / 85 / 90');
        $this->guard($this->actualValues($source, $codec) === [80.0, 85.0, 90.0], 'week-1 source actuals are no longer 80 / 85 / 90');
        $this->guard($this->plannedValues($target, $codec) === [5.0, 5.0, 5.0], 'week-3 target plan is no longer exactly 5 / 5 / 5');
        $this->guard($this->actualValues($target, $codec) === [90.0, 94.0, 94.0], 'week-3 target actuals are no longer 90 / 94 / 94');

        foreach ($this->weightRows($source) as $row) {
            $this->guard(
                $row->actual_recorded_by === self::USER_ID
                    && $row->actual_source === 'athlete'
                    && $row->actual_is_explicit,
                "week-1 source row {$row->id} is not an explicit athlete actual",
            );
        }

        return [$source, $target];
    }

    private function assertUnchangedSessionState(
        TrainingProgramSlotExercise $source,
        TrainingProgramSlotExercise $target,
        TrainingValueSnapshotCodec $codec,
    ): void {
        $source->refresh();
        $target->refresh();
        $target->load('sets.values');

        $this->guard($source->slot?->status === TrainingProgramSlotStatusEnum::Completed, 'source session completion changed');
        $this->guard($target->slot?->status === TrainingProgramSlotStatusEnum::Completed, 'target session completion changed');
        $this->guard($this->plannedValues($target, $codec) === [80.0, 85.0, 90.0], 'corrected target plan was not persisted');
        $this->guard($this->actualValues($target, $codec) === [90.0, 94.0, 94.0], 'target actuals changed');
    }

    private function renderReport(TrainingProgramSlotExercise $target, TrainingValueSnapshotCodec $codec): void
    {
        $rows = [];

        foreach ($this->weightRows($target) as $setIndex => $row) {
            $rows[] = [
                $row->id,
                $setIndex + 1,
                $this->number($codec->extractPlannedValue($row)).' kg',
                self::EXPECTED_PLAN[$setIndex].' kg',
                $this->number($codec->extractActualValue($row)).' kg',
            ];
        }

        $this->info('Affected completed planned rows — actuals remain unchanged');
        $this->table(['Value row', 'Set', 'Plan before', 'Plan after', 'Actual'], $rows);
        $this->line('Review: '.$this->calendarUrl());
    }

    /** @return array<int, TrainingProgramSlotSetValue> */
    private function weightRows(TrainingProgramSlotExercise $exercise): array
    {
        $rows = [];

        foreach ($exercise->sets->sortBy('set_number')->values() as $setIndex => $set) {
            $row = $set->values->firstWhere('setting_key', 'weight');
            $this->guard($row instanceof TrainingProgramSlotSetValue, "weight row missing at set {$setIndex}");
            $rows[$setIndex] = $row;
        }

        $this->guard(count($rows) === 3, 'expected exactly three weight rows');

        return $rows;
    }

    /** @return list<float> */
    private function plannedValues(TrainingProgramSlotExercise $exercise, TrainingValueSnapshotCodec $codec): array
    {
        return array_values(array_map(
            fn (TrainingProgramSlotSetValue $row): float => (float) $codec->extractPlannedValue($row),
            $this->weightRows($exercise),
        ));
    }

    /** @return list<float> */
    private function actualValues(TrainingProgramSlotExercise $exercise, TrainingValueSnapshotCodec $codec): array
    {
        return array_values(array_map(
            fn (TrainingProgramSlotSetValue $row): float => (float) $codec->extractActualValue($row),
            $this->weightRows($exercise),
        ));
    }

    /** @return array<string, mixed> */
    private function actualSnapshot(TrainingProgramSlotSetValue $row): array
    {
        $snapshot = [];

        foreach ([
            'actual_value_type',
            'actual_int_value',
            'actual_decimal_value',
            'actual_string_value',
            'actual_json_value',
            'actual_recorded_by',
            'actual_recorded_at',
            'actual_source',
            'actual_is_explicit',
        ] as $attribute) {
            $snapshot[$attribute] = $row->getRawOriginal($attribute);
        }

        return $snapshot;
    }

    private function calendarUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/admin/calendar?'.http_build_query([
            'preset' => 'thisMonth',
            'groupFilter' => 'all',
            'group' => 40,
            'user' => self::USER_ID,
            'planCategory' => 7,
            'planBlock' => 121,
            'planProgram' => 213,
            'view' => 'plan',
        ]);
    }

    private function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    private function guard(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
