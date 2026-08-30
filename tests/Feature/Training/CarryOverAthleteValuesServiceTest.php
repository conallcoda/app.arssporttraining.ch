<?php

use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Data\Exercise\Settings\WeightSetting;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingPlanValueRevision;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\AthleteExerciseValueService;
use App\Training\CarryOverAthleteValuesService;
use App\Training\TrainingSessionMaterializer;
use App\Training\TrainingSessionProgressService;
use App\Training\TrainingValueSnapshotCodec;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes carry-over athlete values as enabled by default for manual weight settings', function () {
    $fields = collect(WeightSetting::fields());
    $field = $fields
        ->firstWhere('name', 'carryOverAthleteValues');

    expect($field)->not->toBeNull()
        ->and($field->type)->toBe('switch')
        ->and($field->default)->toBeTrue()
        ->and($field->showExpression)->toBe('mode == "manual"')
        ->and($fields->pluck('name')->values()->all())->toBe([
            'mode',
            'oneRepMaxModifier',
            'default',
            'applyPer',
            'carryOverAthleteValues',
        ])
        ->and((new WeightSetting)->carryOverAthleteValues)->toBeTrue();
});

it('carries athlete-entered weights and reps to future planned values without writing future actuals', function () {
    [$athlete, $pivot, $trainingProgram] = carryOverProgram([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 3, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 40, 'applyPer' => 'set'],
    ]);

    $sourceSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-01 09:00:00');
    $futureSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-08 09:00:00');

    $sourceExercise = carryOverSlotExercise($sourceSlot, $pivot->id);
    $futureExercise = carryOverSlotExercise($futureSlot, $pivot->id);
    $sourceSets = $sourceExercise->sets->sortBy('set_number')->values();

    $this->actingAs($athlete);

    carryOverSaveAndComplete($sourceExercise, [
        $sourceSets[0]->id => ['weight' => 42.5, 'reps' => 6],
        $sourceSets[1]->id => ['weight' => 45, 'reps' => 7],
        $sourceSets[2]->id => ['weight' => 47.5, 'reps' => 8],
    ], onlyProvided: true);

    expect(carryOverPlannedValues($sourceExercise, 'weight'))->toBe([40.0, 40.0, 40.0])
        ->and(carryOverPlannedValues($sourceExercise, 'reps'))->toBe(['5', '5', '5'])
        ->and(carryOverPlannedValues($futureExercise, 'weight'))->toBe([42.5, 45.0, 47.5])
        ->and(carryOverPlannedValues($futureExercise, 'reps'))->toBe(['6', '7', '8'])
        ->and(carryOverActualValues($futureExercise, 'weight'))->toBe([null, null, null])
        ->and(carryOverActualValues($futureExercise, 'reps'))->toBe([null, null, null]);

    $gridOverrides = carryOverGridOverrides($trainingProgram, $pivot->id, $athlete->id);

    expect(carryOverOverrideCellData($gridOverrides, 0, 1, 0))->toBe(['reps' => '6', 'weight' => 42.5])
        ->and(carryOverOverrideCellData($gridOverrides, 0, 1, 1))->toBe(['reps' => '7', 'weight' => 45])
        ->and(carryOverOverrideCellData($gridOverrides, 0, 1, 2))->toBe(['reps' => '8', 'weight' => 47.5]);

    carryOverClearGridOverrides($trainingProgram, $pivot->id, $athlete->id);

    app(CarryOverAthleteValuesService::class)->carryFrom($sourceExercise);

    $gridOverrides = carryOverGridOverrides($trainingProgram, $pivot->id, $athlete->id);

    expect(carryOverOverrideCellData($gridOverrides, 0, 1, 0))->toBe(['reps' => '6', 'weight' => 42.5])
        ->and(carryOverOverrideCellData($gridOverrides, 0, 1, 1))->toBe(['reps' => '7', 'weight' => 45])
        ->and(carryOverOverrideCellData($gridOverrides, 0, 1, 2))->toBe(['reps' => '8', 'weight' => 47.5]);
});

it('does not carry athlete values until the source session is completed', function () {
    [$athlete, $pivot, $trainingProgram] = carryOverProgram([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 40, 'applyPer' => 'set'],
    ]);
    $sourceSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-01 09:00:00');
    $futureSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-08 09:00:00');
    $sourceExercise = carryOverSlotExercise($sourceSlot, $pivot->id);
    $futureExercise = carryOverSlotExercise($futureSlot, $pivot->id);

    $this->actingAs($athlete);
    app(AthleteExerciseValueService::class)->saveExerciseValues($sourceExercise, [
        $sourceExercise->sets->first()->id => ['weight' => 50],
    ], onlyProvided: true);

    expect(carryOverPlannedValues($futureExercise, 'weight'))->toBe([40.0]);

    app(TrainingSessionProgressService::class)->markExerciseCompleted($sourceExercise);

    expect(carryOverPlannedValues($futureExercise, 'weight'))->toBe([50.0]);
});

it('preserves a target cell explicitly planned by a coach after the athlete actual', function () {
    CarbonImmutable::setTestNow('2030-04-01 10:00:00');
    [$athlete, $pivot, $trainingProgram] = carryOverProgram([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 40, 'applyPer' => 'set'],
        'preview' => ['weeks' => 2, 'groupingMode' => 'none'],
    ]);
    $sourceSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-01 09:00:00');
    $futureSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-08 09:00:00');
    $sourceExercise = carryOverSlotExercise($sourceSlot, $pivot->id);
    $futureExercise = carryOverSlotExercise($futureSlot, $pivot->id);

    $this->actingAs($athlete);
    app(AthleteExerciseValueService::class)->saveExerciseValues($sourceExercise, [
        $sourceExercise->sets->first()->id => ['weight' => 50],
    ], onlyProvided: true);

    CarbonImmutable::setTestNow('2030-04-01 11:00:00');
    $futureExercise->sets->first()->values->firstWhere('setting_key', 'weight')->update([
        'planned_value_type' => 'decimal',
        'planned_decimal_value' => 55,
    ]);
    $batch = TrainingRevisionBatch::create([
        'owner_type' => ExerciseProgram::class,
        'owner_id' => $trainingProgram->exercise_program_id,
        'domain' => 'plan',
        'action' => 'save_grid_overrides',
        'source' => 'coach',
    ]);
    TrainingPlanValueRevision::create([
        'batch_id' => $batch->id,
        'owner_type' => ExerciseProgram::class,
        'owner_id' => $trainingProgram->exercise_program_id,
        'program_exercise_id' => $pivot->id,
        'user_id' => $athlete->id,
        'setting_key' => 'weight',
        'week_index' => 1,
        'session_index' => 0,
        'set_index' => 0,
        'source' => 'coach',
        'after_value_type' => 'decimal',
        'after_decimal_value' => 55,
        'unit' => 'kg',
    ]);

    CarbonImmutable::setTestNow('2030-04-01 12:00:00');
    app(TrainingSessionProgressService::class)->markExerciseCompleted($sourceExercise);

    expect(carryOverPlannedValues($futureExercise, 'weight'))->toBe([55.0]);
    CarbonImmutable::setTestNow();
});

it('does not let a retrospectively completed older session override a newer completed source', function () {
    [$athlete, $pivot, $trainingProgram] = carryOverProgram([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
        'preview' => ['weeks' => 3, 'groupingMode' => 'none'],
    ]);
    $olderSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-01 09:00:00');
    $newerSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-08 09:00:00');
    $futureSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-15 09:00:00');
    $olderExercise = carryOverSlotExercise($olderSlot, $pivot->id);
    $newerExercise = carryOverSlotExercise($newerSlot, $pivot->id);
    $futureExercise = carryOverSlotExercise($futureSlot, $pivot->id);

    $this->actingAs($athlete);
    carryOverSaveAndComplete($newerExercise, [
        $newerExercise->sets->first()->id => ['weight' => 20],
    ], onlyProvided: true);
    carryOverSaveAndComplete($olderExercise, [
        $olderExercise->sets->first()->id => ['weight' => 10],
    ], onlyProvided: true);

    expect(carryOverPlannedValues($futureExercise, 'weight'))->toBe([20.0]);
});

it('stores carry-over overrides at canonical coordinates for ungrouped and fixed-group sessions', function (string $groupingMode) {
    [$athlete, $pivot, $trainingProgram] = carryOverProgram([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 40, 'applyPer' => 'set'],
        'preview' => [
            'weeks' => 4,
            'sessionsPerWeek' => 1,
            'groupingMode' => $groupingMode,
            'groupSize' => $groupingMode === 'groups' ? 2 : 1,
            'copyValuesAutomatically' => false,
        ],
    ]);

    $sourceSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-01 09:00:00');
    $futureSlots = collect([
        '2030-04-03 09:00:00',
        '2030-04-08 09:00:00',
        '2030-04-10 09:00:00',
    ])->map(fn (string $dateTime) => carryOverSlot($trainingProgram, $athlete, $dateTime));

    $sourceExercise = carryOverSlotExercise($sourceSlot, $pivot->id);

    $this->actingAs($athlete);

    carryOverSaveAndComplete($sourceExercise, [
        $sourceExercise->sets->first()->id => ['weight' => 50, 'reps' => 7],
    ], onlyProvided: true);

    foreach ($futureSlots as $futureSlot) {
        $futureExercise = carryOverSlotExercise($futureSlot, $pivot->id);

        expect(carryOverPlannedValues($futureExercise, 'weight'))->toBe([50.0])
            ->and(carryOverPlannedValues($futureExercise, 'reps'))->toBe(['7']);
    }

    $gridOverrides = carryOverGridOverrides($trainingProgram, $pivot->id, $athlete->id);

    $expectedPositions = $groupingMode === 'groups'
        ? [[0, 1], [1, 0], [1, 1]]
        : [[1, 0], [2, 0], [3, 0]];

    foreach ($expectedPositions as [$week, $session]) {
        expect(carryOverOverrideCellData($gridOverrides, $week, $session, 0))
            ->toBe(['reps' => '7', 'weight' => 50]);
    }
})->with(['none', 'groups']);

it('carries explicit athlete values even when they equal the source plan', function () {
    [$athlete, $pivot, $trainingProgram] = carryOverProgram([
        'settings' => ['reps', 'weight', 'rest', 'tempo'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 40, 'applyPer' => 'set'],
        'rest' => ['default' => 60, 'applyPer' => 'per_session'],
        'tempo' => ['default' => '3010', 'applyPer' => 'per_session'],
    ]);

    $sourceSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-01 09:00:00');
    $futureSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-08 09:00:00');

    $sourceExercise = carryOverSlotExercise($sourceSlot, $pivot->id);
    $futureExercise = carryOverSlotExercise($futureSlot, $pivot->id);
    $sourceSet = $sourceExercise->sets->first();
    $futureValues = $futureExercise->sets->first()->values;

    $futureValues->firstWhere('setting_key', 'reps')->update([
        'planned_value_type' => 'string',
        'planned_string_value' => '9',
        'planned_int_value' => null,
        'planned_decimal_value' => null,
    ]);
    $futureValues->firstWhere('setting_key', 'weight')->update([
        'planned_value_type' => 'decimal',
        'planned_decimal_value' => 41,
        'planned_int_value' => null,
        'planned_string_value' => null,
    ]);
    $futureValues->firstWhere('setting_key', 'rest')->update([
        'planned_value_type' => 'int',
        'planned_int_value' => 75,
        'planned_decimal_value' => null,
        'planned_string_value' => null,
    ]);
    $futureValues->firstWhere('setting_key', 'tempo')->update([
        'planned_value_type' => 'string',
        'planned_string_value' => '4010',
        'planned_int_value' => null,
        'planned_decimal_value' => null,
    ]);

    $this->actingAs($athlete);

    carryOverSaveAndComplete($sourceExercise, [
        $sourceSet->id => [
            'reps' => 5,
            'weight' => 50,
            'rest' => 90,
            'tempo' => '2010',
        ],
    ], onlyProvided: true);

    expect(carryOverPlannedValues($sourceExercise, 'reps'))->toBe(['5'])
        ->and(carryOverPlannedValues($sourceExercise, 'weight'))->toBe([40.0])
        ->and(carryOverPlannedValues($sourceExercise, 'rest'))->toBe([60])
        ->and(carryOverPlannedValues($sourceExercise, 'tempo'))->toBe(['3010'])
        ->and(carryOverPlannedValues($futureExercise, 'reps'))->toBe(['5'])
        ->and(carryOverPlannedValues($futureExercise, 'weight'))->toBe([50.0])
        ->and(carryOverPlannedValues($futureExercise, 'rest'))->toBe([90])
        ->and(carryOverPlannedValues($futureExercise, 'tempo'))->toBe(['2010']);

    $gridOverrides = carryOverGridOverrides($trainingProgram, $pivot->id, $athlete->id);

    expect(carryOverOverrideCellData($gridOverrides, 0, 1, 0))->toBe(['reps' => '5', 'weight' => 50])
        ->and(carryOverOverrideSessionData($gridOverrides, 0, 1))->toBe(['rest' => 90, 'tempo' => '2010']);
});

it('persists carry-over values for a scheduled future slot before it is compiled', function () {
    [$athlete, $pivot, $trainingProgram] = carryOverProgram([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 3, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
        'preview' => [
            'weeks' => 2,
            'sessionsPerWeek' => 1,
            'groupingMode' => 'none',
            'groupSize' => 1,
            'copyValuesAutomatically' => false,
        ],
    ]);

    $sourceSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-01 09:00:00');
    $futureSlot = TrainingProgramSlot::withoutEvents(fn () => TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-08 09:00:00',
        'scheduled_date' => '2030-04-08',
        'session_index' => 1,
    ]));
    $sourceExercise = carryOverSlotExercise($sourceSlot, $pivot->id);
    $sourceSets = $sourceExercise->sets->sortBy('set_number')->values();

    expect($futureSlot->exercises()->count())->toBe(0);

    $this->actingAs($athlete);

    carryOverSaveAndComplete($sourceExercise, [
        $sourceSets[0]->id => ['weight' => 80, 'reps' => 8],
        $sourceSets[1]->id => ['weight' => 85, 'reps' => 8],
        $sourceSets[2]->id => ['weight' => 90, 'reps' => 8],
    ], onlyProvided: true);

    $gridOverrides = carryOverGridOverrides($trainingProgram, $pivot->id, $athlete->id);

    expect(carryOverOverrideCellData($gridOverrides, 1, 0, 0))->toBe(['reps' => '8', 'weight' => 80])
        ->and(carryOverOverrideCellData($gridOverrides, 1, 0, 1))->toBe(['reps' => '8', 'weight' => 85])
        ->and(carryOverOverrideCellData($gridOverrides, 1, 0, 2))->toBe(['reps' => '8', 'weight' => 90]);

    app(TrainingSessionMaterializer::class)->materialize($futureSlot);
    $futureExercise = carryOverSlotExercise($futureSlot, $pivot->id);

    expect(carryOverPlannedValues($futureExercise, 'weight'))->toBe([80.0, 85.0, 90.0])
        ->and(carryOverPlannedValues($futureExercise, 'reps'))->toBe(['8', '8', '8']);
});

it('preserves source set positions when only a later set differs from the plan', function () {
    [$athlete, $pivot, $trainingProgram] = carryOverProgram([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 4, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 14, 'applyPer' => 'set'],
    ]);

    $sourceSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-01 09:00:00');
    $futureSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-08 09:00:00');

    $sourceExercise = carryOverSlotExercise($sourceSlot, $pivot->id);
    $futureExercise = carryOverSlotExercise($futureSlot, $pivot->id);
    $sourceSets = $sourceExercise->sets->sortBy('set_number')->values();

    $this->actingAs($athlete);

    carryOverSaveAndComplete($sourceExercise, [
        $sourceSets[0]->id => ['weight' => 14, 'reps' => 10],
        $sourceSets[1]->id => ['weight' => 14, 'reps' => 10],
        $sourceSets[2]->id => ['weight' => 14, 'reps' => 8],
        $sourceSets[3]->id => ['weight' => 0, 'reps' => 0],
    ], onlyProvided: true);

    expect(carryOverPlannedValues($futureExercise, 'reps'))->toBe(['10', '10', '8', '0'])
        ->and(carryOverPlannedValues($futureExercise, 'weight'))->toBe([14.0, 14.0, 14.0, 0.0]);

    $gridOverrides = carryOverGridOverrides($trainingProgram, $pivot->id, $athlete->id);

    expect(carryOverOverrideCellData($gridOverrides, 0, 1, 0))->toBe(['reps' => '10', 'weight' => 14])
        ->and(carryOverOverrideCellData($gridOverrides, 0, 1, 1))->toBe(['reps' => '10', 'weight' => 14])
        ->and(carryOverOverrideCellData($gridOverrides, 0, 1, 2))->toBe(['reps' => '8', 'weight' => 14])
        ->and(carryOverOverrideCellData($gridOverrides, 0, 1, 3))->toBe(['reps' => '0', 'weight' => 0]);
});

it('repeats the last source value for extra future sets and treats missing carry-over config as enabled', function () {
    [$athlete, $pivot, $trainingProgram] = carryOverProgram([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 40, 'applyPer' => 'set'],
    ]);

    $sourceSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-01 09:00:00');
    $futureSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-08 09:00:00');

    $futureExercise = carryOverSlotExercise($futureSlot, $pivot->id);
    $futureExercise->sets()->create(['set_number' => 3]);
    $extraSet = $futureExercise->sets()->where('set_number', 3)->firstOrFail();
    foreach (['reps', 'weight'] as $field) {
        $extraSet->values()->create([
            'setting_key' => $field,
            'planned_value_type' => null,
        ]);
    }

    $sourceExercise = carryOverSlotExercise($sourceSlot, $pivot->id);
    $sourceSets = $sourceExercise->sets->sortBy('set_number')->values();

    $this->actingAs($athlete);

    carryOverSaveAndComplete($sourceExercise, [
        $sourceSets[0]->id => ['weight' => 42.5, 'reps' => 6],
        $sourceSets[1]->id => ['weight' => 45, 'reps' => 7],
    ], onlyProvided: true);

    $futureExercise = $futureExercise->fresh('sets.values');

    expect(carryOverPlannedValues($futureExercise, 'weight'))->toBe([42.5, 45.0, 45.0])
        ->and(carryOverPlannedValues($futureExercise, 'reps'))->toBe(['6', '7', '7']);
});

it('does not carry values when the toggle is disabled or the future exercise already has actual values', function () {
    [$athlete, $disabledPivot, $disabledTrainingProgram] = carryOverProgram([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
        'weight' => [
            'mode' => 'manual',
            'default' => 40,
            'carryOverAthleteValues' => false,
            'applyPer' => 'set',
        ],
    ]);

    $disabledSourceSlot = carryOverSlot($disabledTrainingProgram, $athlete, '2030-04-01 09:00:00');
    $disabledFutureSlot = carryOverSlot($disabledTrainingProgram, $athlete, '2030-04-08 09:00:00');
    $disabledSourceExercise = carryOverSlotExercise($disabledSourceSlot, $disabledPivot->id);
    $disabledFutureExercise = carryOverSlotExercise($disabledFutureSlot, $disabledPivot->id);
    $disabledSourceSet = $disabledSourceExercise->sets->first();

    $this->actingAs($athlete);

    carryOverSaveAndComplete($disabledSourceExercise, [
        $disabledSourceSet->id => ['weight' => 60, 'reps' => 9],
    ], onlyProvided: true);

    expect(carryOverPlannedValues($disabledFutureExercise, 'weight'))->toBe([40.0])
        ->and(carryOverPlannedValues($disabledFutureExercise, 'reps'))->toBe(['5']);

    [$athlete, $pivot, $trainingProgram] = carryOverProgram([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 40, 'applyPer' => 'set'],
    ]);

    $sourceSlot = carryOverSlot($trainingProgram, $athlete, '2030-05-01 09:00:00');
    $futureSlot = carryOverSlot($trainingProgram, $athlete, '2030-05-08 09:00:00');
    $sourceExercise = carryOverSlotExercise($sourceSlot, $pivot->id);
    $futureExercise = carryOverSlotExercise($futureSlot, $pivot->id);
    $sourceSet = $sourceExercise->sets->first();

    $futureExercise->sets->first()->values->firstWhere('setting_key', 'weight')->update([
        'actual_value_type' => 'decimal',
        'actual_decimal_value' => 41,
        'actual_is_explicit' => true,
    ]);

    $this->actingAs($athlete);

    carryOverSaveAndComplete($sourceExercise, [
        $sourceSet->id => ['weight' => 62.5, 'reps' => 10],
    ], onlyProvided: true);

    expect(carryOverPlannedValues($futureExercise, 'weight'))->toBe([40.0])
        ->and(carryOverPlannedValues($futureExercise, 'reps'))->toBe(['5']);
});

it('skips already recorded future sessions while updating later unrecorded sessions', function () {
    [$athlete, $pivot, $trainingProgram] = carryOverProgram([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 7.5, 'applyPer' => 'set'],
    ]);

    $retroSlot = carryOverSlot($trainingProgram, $athlete, '2030-06-15 09:00:00');
    $recordedFutureSlot = carryOverSlot($trainingProgram, $athlete, '2030-06-17 09:00:00');
    $openFutureSlot = carryOverSlot($trainingProgram, $athlete, '2030-06-19 09:00:00');

    $retroExercise = carryOverSlotExercise($retroSlot, $pivot->id);
    $recordedFutureExercise = carryOverSlotExercise($recordedFutureSlot, $pivot->id);
    $openFutureExercise = carryOverSlotExercise($openFutureSlot, $pivot->id);

    $recordedFutureExercise->sets->first()->values->firstWhere('setting_key', 'weight')->update([
        'actual_value_type' => 'decimal',
        'actual_decimal_value' => 8,
        'actual_is_explicit' => true,
    ]);

    $this->actingAs($athlete);

    carryOverSaveAndComplete($retroExercise, [
        $retroExercise->sets->first()->id => ['weight' => 10, 'reps' => 14],
    ], onlyProvided: true);

    expect(carryOverPlannedValues($recordedFutureExercise, 'weight'))->toBe([7.5])
        ->and(carryOverPlannedValues($recordedFutureExercise, 'reps'))->toBe(['12'])
        ->and(carryOverPlannedValues($openFutureExercise, 'weight'))->toBe([10.0])
        ->and(carryOverPlannedValues($openFutureExercise, 'reps'))->toBe(['14']);
});

it('does not carry into past unrecorded sessions after a retrospective completion', function () {
    CarbonImmutable::setTestNow('2030-06-19 12:00:00');

    [$athlete, $pivot, $trainingProgram] = carryOverProgram([
        'settings' => ['reps', 'weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 7.5, 'applyPer' => 'set'],
    ]);

    $sourceSlot = carryOverSlot($trainingProgram, $athlete, '2030-06-15 09:00:00');
    $pastUnrecordedSlot = carryOverSlot($trainingProgram, $athlete, '2030-06-17 09:00:00');
    $openFutureSlot = carryOverSlot($trainingProgram, $athlete, '2030-06-22 09:00:00');

    $sourceExercise = carryOverSlotExercise($sourceSlot, $pivot->id);
    $pastUnrecordedExercise = carryOverSlotExercise($pastUnrecordedSlot, $pivot->id);
    $openFutureExercise = carryOverSlotExercise($openFutureSlot, $pivot->id);

    $this->actingAs($athlete);

    carryOverSaveAndComplete($sourceExercise, [
        $sourceExercise->sets->first()->id => ['weight' => 10, 'reps' => 14],
    ], onlyProvided: true);

    $overrides = carryOverOverrides($trainingProgram, $pivot->id, $athlete->id);

    expect(carryOverPlannedValues($pastUnrecordedExercise, 'weight'))->toBe([7.5])
        ->and(carryOverPlannedValues($openFutureExercise, 'weight'))->toBe([10.0])
        ->and(carryOverOverrideCellData($overrides->historicalGridOverrides, 0, 1, 0))->toBe([])
        ->and(carryOverOverrideCellData($overrides->gridOverrides, 0, 1, 0))->toBe([])
        ->and(carryOverOverrideCellData($overrides->gridOverrides, 1, 0, 0))->toBe(['reps' => '14', 'weight' => 10]);

    CarbonImmutable::setTestNow();
});

it('matches future exercises by program exercise id instead of exercise id sort or group', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps', 'weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
            'weight' => ['mode' => 'manual', 'default' => 40, 'applyPer' => 'set'],
        ],
    ]);
    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'group' => 'A',
        'type' => 'main',
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 1,
        'group' => 'B',
        'type' => 'main',
    ]);

    $trainingProgram = TrainingProgram::importProgram($program, $group->id);
    $pivots = $trainingProgram->program->fresh(['exercises'])->exercises
        ->map(fn (Exercise $exercise) => $exercise->pivot)
        ->sortBy('sort')
        ->values();

    $sourcePivot = $pivots[0];
    $duplicatePivot = $pivots[1];

    $sourceSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-01 09:00:00');
    $futureSlot = carryOverSlot($trainingProgram, $athlete, '2030-04-08 09:00:00');

    $sourceExercise = carryOverSlotExercise($sourceSlot, $sourcePivot->id);
    $futureSourceExercise = carryOverSlotExercise($futureSlot, $sourcePivot->id);
    $futureDuplicateExercise = carryOverSlotExercise($futureSlot, $duplicatePivot->id);
    $sourceSet = $sourceExercise->sets->first();

    $sourceExercise->forceFill(['sort' => 99, 'group' => 'Z'])->save();
    $futureSourceExercise->forceFill(['sort' => 42, 'group' => 'Y'])->save();

    $this->actingAs($athlete);

    carryOverSaveAndComplete($sourceExercise, [
        $sourceSet->id => ['weight' => 70, 'reps' => 11],
    ], onlyProvided: true);
    app(TrainingSessionProgressService::class)->markExerciseCompleted(
        carryOverSlotExercise($sourceSlot, $duplicatePivot->id),
    );

    expect(carryOverPlannedValues($futureSourceExercise, 'weight'))->toBe([70.0])
        ->and(carryOverPlannedValues($futureSourceExercise, 'reps'))->toBe(['11'])
        ->and(carryOverPlannedValues($futureDuplicateExercise, 'weight'))->toBe([40.0])
        ->and(carryOverPlannedValues($futureDuplicateExercise, 'reps'))->toBe(['5']);
});

function carryOverProgram(array $exerciseConfig): array
{
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $exercise = Exercise::factory()->create(['config' => $exerciseConfig]);
    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $trainingProgram = TrainingProgram::importProgram($program, $group->id);
    $pivot = $trainingProgram->program->fresh(['exercises'])->exercises->first()->pivot;

    if (isset($exerciseConfig['preview']['groupingMode'])) {
        $scheduledProgram = $trainingProgram->program->fresh();
        $config = $scheduledProgram->config;
        $overrides = $config->defaultExerciseOverrides($pivot->id);
        $overrides->sessionGrouping = SessionGroupingConfig::from([
            'mode' => $exerciseConfig['preview']['groupingMode'],
            'groupSize' => $exerciseConfig['preview']['groupSize'] ?? null,
            'copyValuesAutomatically' => $exerciseConfig['preview']['copyValuesAutomatically'] ?? false,
        ]);
        $config->setDefaultExerciseOverrides($pivot->id, $overrides);
        $scheduledProgram->config = $config;
        $scheduledProgram->save();
    }

    return [$athlete, $pivot, $trainingProgram];
}

function carryOverSaveAndComplete(
    TrainingProgramSlotExercise $exercise,
    array $submittedValues,
    bool $onlyProvided = false,
): bool {
    $changed = app(AthleteExerciseValueService::class)->saveExerciseValues(
        $exercise,
        $submittedValues,
        $onlyProvided,
    );
    app(TrainingSessionProgressService::class)->markExerciseCompleted($exercise);

    return $changed;
}

function carryOverSlot(TrainingProgram $trainingProgram, User $athlete, string $dateTime): TrainingProgramSlot
{
    return TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => $dateTime,
        'scheduled_date' => substr($dateTime, 0, 10),
    ])->fresh('exercises.sets.values');
}

function carryOverSlotExercise(TrainingProgramSlot $slot, int $programExerciseId): TrainingProgramSlotExercise
{
    return $slot->fresh('exercises.sets.values')
        ->exercises
        ->firstWhere('exercise_program_exercise_id', $programExerciseId)
        ->load('sets.values');
}

function carryOverPlannedValues(TrainingProgramSlotExercise $exercise, string $field): array
{
    $codec = app(TrainingValueSnapshotCodec::class);

    return $exercise->fresh('sets.values')
        ->sets
        ->sortBy('set_number')
        ->values()
        ->map(fn ($set) => $set->values->firstWhere('setting_key', $field))
        ->map(fn (?TrainingProgramSlotSetValue $value) => $value ? $codec->extractPlannedValue($value) : null)
        ->all();
}

function carryOverActualValues(TrainingProgramSlotExercise $exercise, string $field): array
{
    $codec = app(TrainingValueSnapshotCodec::class);

    return $exercise->fresh('sets.values')
        ->sets
        ->sortBy('set_number')
        ->values()
        ->map(fn ($set) => $set->values->firstWhere('setting_key', $field))
        ->map(fn (?TrainingProgramSlotSetValue $value) => $value ? $codec->extractActualValue($value) : null)
        ->all();
}

function carryOverGridOverrides(TrainingProgram $trainingProgram, int $programExerciseId, int $userId): array
{
    return carryOverOverrides($trainingProgram, $programExerciseId, $userId)
        ->gridOverrides;
}

function carryOverOverrides(TrainingProgram $trainingProgram, int $programExerciseId, int $userId)
{
    return $trainingProgram->program
        ->fresh()
        ->config
        ->exerciseOverrides($programExerciseId, $userId);
}

function carryOverClearGridOverrides(TrainingProgram $trainingProgram, int $programExerciseId, int $userId): void
{
    $program = $trainingProgram->program->fresh();
    $config = $program->config;
    $config->removeUserExerciseOverrides($userId, $programExerciseId);
    $program->config = $config;
    $program->save();
}

function carryOverOverrideCellData(array $gridOverrides, int $week, int $session, int $set): array
{
    $data = collect($gridOverrides['cells'] ?? [])
        ->first(fn (array $row): bool => ($row['week'] ?? null) === $week
            && ($row['session'] ?? null) === $session
            && ($row['set'] ?? null) === $set)['data'] ?? [];

    ksort($data);

    return $data;
}

function carryOverOverrideSessionData(array $gridOverrides, int $week, int $session): array
{
    $data = collect($gridOverrides['sessions'] ?? [])
        ->first(fn (array $row): bool => ($row['week'] ?? null) === $week
            && ($row['session'] ?? null) === $session)['data'] ?? [];

    ksort($data);

    return $data;
}
