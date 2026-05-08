<?php

use App\Data\Training\Config\ExerciseOverrides;
use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionStatusService;
use App\Training\TrainingValueSnapshotCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    $component->call('updateCellOverride', 0, 0, 'weight', 5, 0, false);

    expect(gridRenderVersion($component))->toBe(2);

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

/**
 * @return array{0: User, 1: ExerciseProgram, 2: ExerciseProgramExercise, 3?: Exercise, 4?: TrainingProgram}
 */
function buildScheduledProgramContext(array $exerciseConfig = [
    'settings' => ['reps'],
    'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
    'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
]): array {
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
