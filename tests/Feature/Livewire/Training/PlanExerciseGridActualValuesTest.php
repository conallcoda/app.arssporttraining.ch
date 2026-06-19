<?php

use App\Data\Training\Config\ExerciseOverrides;
use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Support\Athlete\ProgramDetailsExerciseViewBuilder;
use App\Support\Training\ScheduledSessionSnapshotBuilder;
use App\Training\TrainingSessionStatusService;
use App\Training\TrainingValueSnapshotCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('builds the planned grid table with grouping semantics and separate session scoped rows', function () {
    [$athlete, $scheduledProgram, $pivot] = buildScheduledProgramContext([
        'settings' => ['reps', 'rest'],
        'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
        'rest' => ['default' => 60, 'applyPer' => 'week'],
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $pivot->exercise_id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2],
        'weekSessionDates' => [['2026-04-27', '2026-04-30']],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'planned',
    ]);

    $table = plannedTable($component);

    expect($table['mode'])->toBe('planned')
        ->and($table['showsSettingBadges'])->toBeTrue()
        ->and($table['usesGrouping'])->toBeTrue()
        ->and($table['sessionScopedFields'])->toContain('rest');

    $firstSession = $table['groups'][0]['sessions'][0];

    expect(collect($firstSession['setRows'])->pluck('field')->all())->toContain('reps')
        ->and(collect($firstSession['sessionRows'])->pluck('field')->all())->toContain('rest')
        ->and(collect($firstSession['setRows'])->firstWhere('field', 'reps')['cells'])->toBe([12, 12])
        ->and(collect($firstSession['sessionRows'])->firstWhere('field', 'rest')['value'])->toBe(60);
});

it('persists planned override removal when a planned value returns to default', function () {
    [$athlete, $scheduledProgram, $pivot] = buildScheduledProgramContext([
        'settings' => ['weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'weight' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $pivot->exercise_id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'planned',
    ]);

    expect(gridRenderVersion($component))->toBe(0)
        ->and(plannedCellValue($component, 'weight', 0, 0))->toEqual(5);

    $component->call('updateCellOverride', 0, 0, 'weight', 66, 0, false);

    expect(gridRenderVersion($component))->toBe(1)
        ->and(plannedCellValue($component, 'weight', 0, 0))->toEqual(66);
    $component->assertNotDispatched('exercise-overrides-changed');

    $component->call('updateCellOverride', 0, 0, 'weight', 77, 0, false);

    $scheduledProgram->refresh();
    expect($scheduledProgram->config->exerciseOverrides($pivot->id, $athlete->id)->gridOverrides['cells'][0]['data']['weight'] ?? null)->toEqual(77);

    expect(gridRenderVersion($component))->toBe(2)
        ->and(plannedCellValue($component, 'weight', 0, 0))->toEqual(77);

    $component->call('updateCellOverride', 0, 0, 'weight', 5, 0, false);

    expect(gridRenderVersion($component))->toBe(3)
        ->and(plannedCellValue($component, 'weight', 0, 0))->toEqual(5);
    $component->assertNotDispatched('exercise-overrides-changed');

    $scheduledProgram->refresh();
    $savedOverrides = $scheduledProgram->config->exerciseOverrides($pivot->id, $athlete->id);

    expect($savedOverrides->gridOverrides['cells'])->toBeEmpty();

    $freshComponent = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $pivot->exercise_id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'planned',
    ]);

    expect(plannedCellValue($freshComponent, 'weight', 0, 0))->toEqual(5);
});

it('rejects drop-set grid values that do not match the reps part count', function () {
    [$athlete, $scheduledProgram, $pivot] = buildScheduledProgramContext([
        'settings' => ['reps', 'weight'],
        'sets' => ['type' => 'drop', 'default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => '3x12', 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => '6,5,4', 'applyPer' => 'set'],
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $pivot->exercise_id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'planned',
    ]);

    $component->call('updateCellOverride', 0, 0, 'weight', '12,12', 0, false);

    $savedOverrides = $scheduledProgram->fresh()->config->exerciseOverrides($pivot->id, $athlete->id);

    expect(plannedCellValue($component, 'weight', 0, 0))->toEqual('6,5,4')
        ->and($savedOverrides->gridOverrides['cells'])->toBeEmpty();
});

it('filters inherited normal-set grid values when an athlete has drop-set settings', function () {
    [$athlete, $scheduledProgram, $pivot] = buildScheduledProgramContext([
        'settings' => ['reps', 'weight'],
        'sets' => ['type' => 'normal', 'default' => 3, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
    ]);

    $config = $scheduledProgram->config;
    $config->setDefaultExerciseOverrides($pivot->id, ExerciseOverrides::from([
        'sets' => ['type' => 'normal', 'default' => 3, 'label' => 'Set', 'deload' => 'none'],
        'gridOverrides' => [
            'sessions' => [],
            'cells' => [
                ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => '12']],
                ['week' => 0, 'session' => 0, 'set' => 1, 'data' => ['reps' => '12']],
                ['week' => 0, 'session' => 0, 'set' => 2, 'data' => ['reps' => '12']],
            ],
        ],
    ]));
    $config->setExerciseOverrides($pivot->id, ExerciseOverrides::from([
        'sets' => ['type' => 'drop', 'default' => 3, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => '10,10,10', 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => '10,10,10', 'applyPer' => 'set'],
        'gridOverrides' => ['sessions' => [], 'cells' => []],
    ]), $athlete->id);
    $scheduledProgram->forceFill(['config' => $config])->save();

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $pivot->exercise_id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'planned',
    ]);

    expect(plannedCellValue($component, 'reps', 0, 0))->toEqual('10,10,10')
        ->and(plannedCellValue($component, 'weight', 0, 0))->toEqual('10,10,10');
});

it('builds the plan plus actual table without grouping and repeats session scoped values as independent set cells', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps', 'rest'],
        'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
        'rest' => ['default' => 60, 'applyPer' => 'week'],
    ]);

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ])->fresh('exercises.sets.values');

    $slotExercise = $slot->exercises->first();
    $sets = $slotExercise->sets->sortBy('set_number')->values();

    $sets[0]->values->firstWhere('setting_key', 'rest')->update([
        'planned_int_value' => 60,
        'planned_value_type' => 'int',
        'actual_value_type' => 'int',
        'actual_int_value' => 75,
    ]);
    $sets[1]->values->firstWhere('setting_key', 'rest')->update([
        'planned_int_value' => 60,
        'planned_value_type' => 'int',
        'actual_value_type' => 'int',
        'actual_int_value' => 60,
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'actual',
    ]);

    $table = actualTable($component);

    expect($table['mode'])->toBe('actual')
        ->and($table['showsSettingBadges'])->toBeFalse()
        ->and($table['usesGrouping'])->toBeFalse()
        ->and($table['sessions'])->toHaveCount(1);

    $restRow = actualRow($table, 0, 'rest');

    expect(array_column($restRow['cells'], 'planned'))->toBe(['60', '60'])
        ->and(array_column($restRow['cells'], 'actual'))->toBe(['75', '60'])
        ->and($restRow['cells'][0]['actualHighlighted'])->toBeTrue()
        ->and($restRow['cells'][1]['actualHighlighted'])->toBeFalse()
        ->and(collect($table['sessions'][0]['rows'])->pluck('field')->contains('sets'))->toBeFalse();
});

it('matches materialized actual values by program exercise id after slot sort and group drift', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'weight' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'set'],
    ]);

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ])->fresh('exercises.sets.values');

    $slotExercise = $slot->exercises->first();
    $slotExercise->forceFill([
        'sort' => 99,
        'group' => 'Z',
    ])->save();

    $slotExercise->sets->first()->values->firstWhere('setting_key', 'weight')->update([
        'actual_value_type' => 'decimal',
        'actual_decimal_value' => '42.50',
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'actual',
        'programExerciseSort' => 0,
        'programExerciseGroup' => null,
    ]);

    $weightRow = actualRow(actualTable($component), 0, 'weight');

    expect($weightRow['cells'][0]['actual'])->toBe('42.5');
});

it('shows planned values as actuals for completed sets without explicit actual values', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
    ]);

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
        'status' => TrainingProgramSlotStatusEnum::Completed,
        'completed_at' => '2026-04-27 10:00:00',
    ])->fresh('exercises.sets.values');

    $slotExercise = $slot->exercises->first();
    $slotExercise->forceFill([
        'status' => \App\Models\Training\TrainingProgramSlotExerciseStatusEnum::Completed,
        'completed_set_count' => 1,
        'pending_set_count' => 0,
    ])->save();

    $slotExercise->sets->first()->forceFill([
        'status' => \App\Models\Training\TrainingProgramSlotSetStatusEnum::Completed,
        'completed_at' => '2026-04-27 10:00:00',
    ])->save();

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'actual',
    ]);

    $table = actualTable($component);
    $repsRow = actualRow($table, 0, 'reps');
    $weightRow = actualRow($table, 0, 'weight');

    expect($repsRow['cells'][0]['actual'])->toBe('12')
        ->and($weightRow['cells'][0]['actual'])->toBe('5');
});

it('uses the planner resolved value for planned cells in plan plus actual mode', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'weight' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'set'],
    ]);

    TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ]);

    $config = $scheduledProgram->config;
    $config->setExerciseOverrides($pivot->id, ExerciseOverrides::from([
        'historicalGridOverrides' => [
            'cells' => [
                ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['weight' => 22.5]],
            ],
            'sessions' => [],
        ],
    ]), $athlete->id);
    $scheduledProgram->config = $config;
    $scheduledProgram->saveQuietly();

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'lockedSessionsByWeek' => [[true]],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'actual',
    ]);

    $weightRow = actualRow(actualTable($component), 0, 'weight');

    expect($weightRow['cells'][0]['planned'])->toBe('22.5');
});

it('shares scheduled slot data across plan exercise grid instances in one request', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
    ]);

    TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ]);

    $componentPayload = [
        'planId' => $scheduledProgram->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'exerciseName' => $exercise->name,
        'exerciseConfigArray' => $exercise->config->toArray(),
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'actual',
    ];

    actualTable(Livewire::test(PlanExerciseGrid::class, $componentPayload));

    DB::enableQueryLog();
    DB::flushQueryLog();

    actualTable(Livewire::test(PlanExerciseGrid::class, $componentPayload));

    $scheduledTreeQueries = collect(DB::getQueryLog())
        ->filter(function (array $query): bool {
            $sql = $query['query'] ?? '';

            return str_contains($sql, 'training_program_slots')
                || str_contains($sql, 'training_program_slot_exercises')
                || str_contains($sql, 'training_program_slot_sets')
                || str_contains($sql, 'training_program_slot_set_values');
        })
        ->count();

    expect($scheduledTreeQueries)->toBe(0);
});

it('numbers sessions sequentially across the whole block in plan plus actual mode', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
    ]);

    foreach ([
        '2026-04-27 09:00:00',
        '2026-04-30 09:00:00',
        '2026-05-04 09:00:00',
        '2026-05-07 09:00:00',
    ] as $dateTime) {
        TrainingProgramSlot::create([
            'training_program_id' => $trainingProgram->id,
            'user_id' => $athlete->id,
            'datetime' => $dateTime,
            'scheduled_date' => substr($dateTime, 0, 10),
        ]);
    }

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 2,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2, 2],
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
            ['2026-05-04', '2026-05-07'],
        ],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'actual',
    ]);

    expect(collect(actualTable($component)['sessions'])->pluck('sessionNumber')->all())->toBe([1, 2, 3, 4]);
});

it('matches the athlete scheduled exercise rendering for actual values on the same slot', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps', 'rest'],
        'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
        'rest' => ['default' => 60, 'applyPer' => 'week'],
    ]);

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ])->fresh('exercises.sets.values');

    $sets = $slot->exercises->first()->sets->sortBy('set_number')->values();

    $sets[0]->values->firstWhere('setting_key', 'reps')->update([
        'actual_value_type' => 'string',
        'actual_string_value' => '10',
    ]);
    $sets[1]->values->firstWhere('setting_key', 'reps')->update([
        'actual_value_type' => 'string',
        'actual_string_value' => '11',
    ]);
    $sets[0]->values->firstWhere('setting_key', 'rest')->update([
        'actual_value_type' => 'int',
        'actual_int_value' => 75,
    ]);
    $sets[1]->values->firstWhere('setting_key', 'rest')->update([
        'actual_value_type' => 'int',
        'actual_int_value' => 75,
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'actual',
    ]);

    $coachTable = actualTable($component);
    $coachRepsRow = actualRow($coachTable, 0, 'reps');
    $coachRestRow = actualRow($coachTable, 0, 'rest');

    $snapshot = app(ScheduledSessionSnapshotBuilder::class)->build($slot->fresh());
    $snapshotExercise = collect($snapshot->exercises)->firstWhere('exerciseId', $exercise->id);
    $athleteExercise = app(ProgramDetailsExerciseViewBuilder::class)->buildFromSnapshot($snapshotExercise, 0);

    expect(array_column($coachRepsRow['cells'], 'actual'))->toBe($athleteExercise->sessionRows[0]->values)
        ->and(array_column($coachRestRow['cells'], 'actual'))->toBe($athleteExercise->sessionRows[1]->values);
});

it('toggles planned and actual subcolumns under set columns without replacing session-scoped columns', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps', 'tempo', 'rest'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
        'tempo' => ['default' => '3010', 'applyPer' => 'week'],
        'rest' => ['default' => 90, 'applyPer' => 'week'],
    ]);

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
        'status' => TrainingProgramSlotStatusEnum::Completed,
        'completed_at' => '2026-04-27 10:00:00',
    ])->fresh('exercises.sets.values');

    $slot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'reps')->update([
        'actual_value_type' => 'string',
        'actual_string_value' => '10',
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'sessionStatusesByWeek' => [[[
            'value' => TrainingProgramSlotStatusEnum::Completed->value,
            'label' => 'Completed',
            'color' => ['light' => '110 231 183', 'dark' => '52 211 153'],
        ]]],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'planned',
    ]);

    $component
        ->assertSet('showPlannedActualInline', false)
        ->assertSee('Plan and actual');

    $component
        ->call('togglePlannedActualInline')
        ->assertSet('showPlannedActualInline', true)
        ->assertSee('Planned')
        ->assertSee('Actual')
        ->assertSee('Plan only')
        ->assertSee('10')
        ->assertSee('Tempo')
        ->assertSee('Rest (s)')
        ->assertSee('Sets');
});

it('keeps planned edit and reset availability aligned to recorded session locks', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2],
        'weekSessionDates' => [['2026-04-27', '2026-04-29']],
        'lockedSessionsByWeek' => [[true, false]],
        'sessionStatusesByWeek' => [[
            [
                'value' => TrainingProgramSlotStatusEnum::Completed->value,
                'label' => 'Completed',
                'color' => ['light' => '110 231 183', 'dark' => '52 211 153'],
            ],
            [
                'value' => TrainingProgramSlotStatusEnum::Pending->value,
                'label' => 'Pending',
                'color' => ['light' => '228 228 231', 'dark' => '161 161 170'],
            ],
        ]],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'planned',
    ]);

    $html = $component->html();

    expect($component->get('resetMenuOptions')['session:0:0'] ?? null)->toBeFalse()
        ->and($component->get('resetMenuOptions')['session:0:1'] ?? null)->toBeTrue()
        ->and($html)->not->toContain("resetDisplayBucket('session:0:0')")
        ->and($html)->toContain("resetDisplayBucket('session:0:1')")
        ->and($html)->not->toContain('data-week="0"'."\n                                                    data-set=\"0\"\n                                                    data-session=\"0\"")
        ->and($html)->toContain('data-week="0"'."\n                                                    data-set=\"0\"\n                                                    data-session=\"1\"");
});

it('does not fall back to another program section with the same exercise id', function () {
    $athlete = User::factory()->create();
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);

    $sourceProgram = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $sourceProgram->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'warm_up',
        'group' => 'C',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $sourceProgram->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
        'group' => 'A',
    ]);

    $trainingProgram = TrainingProgram::importProgram($sourceProgram, $group->id);
    $scheduledProgram = $trainingProgram->program->fresh(['exercises']);
    $mainPivot = $scheduledProgram->exercises
        ->first(fn (Exercise $programExercise): bool => $programExercise->pivot->type === 'main')
        ->pivot;

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ])->fresh('exercises.sets.values');

    $slot->exercises
        ->first(fn ($slotExercise): bool => $slotExercise->type === 'main')
        ->delete();

    $warmUpSet = $slot->fresh('exercises.sets.values')
        ->exercises
        ->first(fn ($slotExercise): bool => $slotExercise->type === 'warm_up')
        ->sets
        ->first();
    $warmUpSet->values->firstWhere('setting_key', 'reps')->update([
        'actual_value_type' => 'string',
        'actual_string_value' => '99',
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'programExerciseId' => $mainPivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'actual',
    ]);

    $repsRow = actualRow(actualTable($component), 0, 'reps');

    expect($repsRow['cells'][0]['actual'])->toBe('-');
});

it('loads scheduled actual snapshots for multiple sessions without per-session query fanout', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps', 'rest'],
        'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
        'rest' => ['default' => 60, 'applyPer' => 'week'],
    ]);

    foreach (range(1, 10) as $sort) {
        $siblingExercise = Exercise::factory()->create([
            'config' => [
                'settings' => ['reps', 'rest'],
                'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
                'rest' => ['default' => 60, 'applyPer' => 'week'],
            ],
        ]);

        ExerciseProgramExercise::create([
            'exercise_program_id' => $scheduledProgram->id,
            'exercise_id' => $siblingExercise->id,
            'sort' => $sort,
            'type' => 'main',
        ]);
    }

    foreach ([
        '2026-04-27 09:00:00',
        '2026-04-30 09:00:00',
        '2026-05-04 09:00:00',
        '2026-05-07 09:00:00',
    ] as $dateTime) {
        TrainingProgramSlot::create([
            'training_program_id' => $trainingProgram->id,
            'user_id' => $athlete->id,
            'datetime' => $dateTime,
            'scheduled_date' => substr($dateTime, 0, 10),
        ]);
    }

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 2,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2, 2],
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
            ['2026-05-04', '2026-05-07'],
        ],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'actual',
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $slotExercises = $component->instance()->slotExercisesByWeekSession();
    $snapshotExercises = $component->instance()->snapshotExercisesByWeekSession();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $snapshotOverfetchQueries = collect($queries)
        ->filter(function (array $query): bool {
            $sql = $query['query'] ?? '';

            return str_contains($sql, 'media')
                || str_contains($sql, 'taggables')
                || str_contains($sql, 'tags');
        })
        ->all();

    expect($slotExercises[0] ?? [])->toHaveCount(2)
        ->and($slotExercises[1] ?? [])->toHaveCount(2)
        ->and($snapshotExercises[0] ?? [])->toHaveCount(2)
        ->and($snapshotExercises[1] ?? [])->toHaveCount(2)
        ->and($snapshotOverfetchQueries)->toBeEmpty()
        ->and(count($queries))->toBeLessThanOrEqual(12);
});

it('keeps the planned grid snapshot sync path for locked historical sessions in planned mode', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'session'],
    ]);

    $coach = User::factory()->coach()->create();

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ])->fresh('exercises.sets.values');

    $slotExercise = $slot->exercises->first();
    $slotSet = $slotExercise->sets->first();
    $value = $slotSet->values->firstWhere('setting_key', 'reps');

    $value->update([
        'actual_value_type' => $value->planned_value_type,
        'actual_int_value' => 5,
        'actual_is_explicit' => true,
    ]);
    $slotSet->update(['completed_at' => now()]);

    app(TrainingSessionStatusService::class)->refreshExerciseState($slotExercise);

    $config = $scheduledProgram->config;
    $config->setExerciseOverrides($pivot->id, ExerciseOverrides::from([
        'historicalGridOverrides' => [
            'cells' => [
                ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 5]],
            ],
            'sessions' => [],
        ],
    ]), $athlete->id);
    $scheduledProgram->config = $config;
    $scheduledProgram->saveQuietly();

    Livewire::actingAs($coach)
        ->test(PlanExerciseGrid::class, [
            'planId' => $scheduledProgram->id,
            'programExerciseId' => $pivot->id,
            'exerciseId' => $pivot->exercise_id,
            'userId' => $athlete->id,
            'weeks' => 1,
            'sessionsPerWeek' => 2,
            'weekSessions' => [2],
            'weekSessionDates' => [['2026-04-27', '2026-04-30']],
            'lockedSessionsByWeek' => [[true, false]],
            'showActualValueTabs' => true,
            'valueDisplayMode' => 'planned',
        ])
        ->call('updateCellOverride', 0, 0, 'reps', 12, 1, false);

    $value = $value->fresh();

    expect((string) app(TrainingValueSnapshotCodec::class)->extractPlannedValue($value))->toBe('5');
});

it('shows the frozen materialized planned snapshot for locked sessions in planned mode', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
    ]);

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ])->fresh('exercises.sets.values');

    $value = $slot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'reps');
    $value->update([
        'planned_value_type' => 'int',
        'planned_int_value' => 8,
        'planned_string_value' => null,
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $pivot->exercise_id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2],
        'weekSessionDates' => [['2026-04-27', '2026-04-30']],
        'lockedSessionsByWeek' => [[true, false]],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'planned',
    ]);

    expect(plannedCellValue($component, 'reps', 0, 0))->toEqual(8)
        ->and(plannedCellValue($component, 'reps', 1, 0))->toEqual(12);
});

it('persists actual-mode planned edits for locked fixed groups through historical overrides and slot snapshots', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'weight' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'set'],
        'preview' => [
            'weeks' => 1,
            'sessionsPerWeek' => 2,
            'groupingMode' => 'groups',
            'groupSize' => 2,
        ],
    ]);

    $coach = User::factory()->coach()->create();

    foreach ([
        '2026-04-27 09:00:00',
        '2026-04-30 09:00:00',
    ] as $dateTime) {
        TrainingProgramSlot::create([
            'training_program_id' => $trainingProgram->id,
            'user_id' => $athlete->id,
            'datetime' => $dateTime,
            'scheduled_date' => substr($dateTime, 0, 10),
        ]);
    }

    Livewire::actingAs($coach)
        ->test(PlanExerciseGrid::class, [
            'planId' => $scheduledProgram->id,
            'scheduledTrainingProgramId' => $trainingProgram->id,
            'programExerciseId' => $pivot->id,
            'exerciseId' => $exercise->id,
            'userId' => $athlete->id,
            'weeks' => 1,
            'sessionsPerWeek' => 2,
            'weekSessions' => [2],
            'weekSessionDates' => [['2026-04-27', '2026-04-30']],
            'lockedSessionsByWeek' => [[true, true]],
            'showActualValueTabs' => true,
            'valueDisplayMode' => 'actual',
        ])
        ->call('updatePlannedDisplayCellValue', 0, 0, 'weight', 22.5, 0);

    $savedOverrides = $scheduledProgram->fresh()->config->exerciseOverrides($pivot->id, $athlete->id);
    $historicalCells = collect($savedOverrides->historicalGridOverrides['cells'] ?? []);

    expect($historicalCells
        ->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 0 && ($cell['session'] ?? null) === 0)['data']['weight'] ?? null)
        ->toBe(22.5)
        ->and($historicalCells
            ->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 0 && ($cell['session'] ?? null) === 1)['data']['weight'] ?? null)
        ->toBe(22.5);

    $slotValues = TrainingProgramSlot::query()
        ->where('training_program_id', $trainingProgram->id)
        ->with('exercises.sets.values')
        ->orderBy('scheduled_date')
        ->get()
        ->map(function (TrainingProgramSlot $slot): mixed {
            $value = $slot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight');

            return app(TrainingValueSnapshotCodec::class)->extractPlannedValue($value);
        })
        ->all();

    expect($slotValues)->toBe([22.5, 22.5]);
});

/**
 * @return array{0: User, 1: ExerciseProgram, 2: ExerciseProgramExercise, 3?: Exercise, 4?: TrainingProgram}
 */
function buildScheduledProgramContext(array $exerciseConfig = [
    'settings' => ['reps'],
    'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
    'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
]): array
{
    $athlete = User::factory()->create();
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $exercise = Exercise::factory()->create([
        'config' => $exerciseConfig,
    ]);

    $sourceProgram = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $sourceProgram->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $trainingProgram = TrainingProgram::importProgram($sourceProgram, $group->id);
    $scheduledProgram = $trainingProgram->program->fresh(['exercises']);
    $pivot = $scheduledProgram->exercises->first()->pivot;

    return [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram];
}

function plannedTable($component): array
{
    return $component->instance()->planGridTable();
}

function actualTable($component): array
{
    return $component->instance()->planActualGridTable();
}

function actualRow(array $table, int $sessionIndex, string $field): array
{
    $session = $table['sessions'][$sessionIndex] ?? null;

    expect($session)->not->toBeNull();

    $row = collect($session['rows'] ?? [])->firstWhere('field', $field);

    expect($row)->not->toBeNull();

    return $row;
}

function gridRenderVersion($component): int
{
    return $component->instance()->gridRenderVersion;
}

function plannedCellValue($component, string $field, int $sessionIndex, int $setIndex): mixed
{
    $session = plannedTable($component)['groups'][0]['sessions'][$sessionIndex] ?? null;

    expect($session)->not->toBeNull();

    $row = collect($session['setRows'] ?? [])->firstWhere('field', $field);

    expect($row)->not->toBeNull();

    return $row['cells'][$setIndex] ?? null;
}
