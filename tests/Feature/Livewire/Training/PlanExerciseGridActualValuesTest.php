<?php

use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Training\Config\ExerciseOverrides;
use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Training\TrainingStateRevision;
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

it('records a completed occurrence through the centralized progress service and audits the coach', function () {
    $coach = User::factory()->coach()->create();
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext();
    $trainingProgram->group()->update(['owner_id' => $coach->id]);

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ])->fresh('exercises.sets.values');

    $slotExercise = $slot->exercises->first();

    Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
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
    ])->call('requestRecordAction', 0, 0, 'completed');

    $recordedValue = $slotExercise->sets->first()->values->first();
    $batch = TrainingRevisionBatch::query()->where('action', 'mark_exercise_completed')->latest('id')->first();

    expect($slotExercise->refresh()->status->value)->toBe('completed')
        ->and($recordedValue->refresh()->actual_value_type)->not->toBeNull()
        ->and($recordedValue->actual_recorded_by)->toBe($coach->id)
        ->and($recordedValue->actual_source)->toBe('coach')
        ->and($batch?->changed_by)->toBe($coach->id)
        ->and($batch?->source)->toBe('coach');
});

it('requires confirmation, replaces stored actual values, and audits admin status changes', function (
    string $action,
    string $initialStatus,
    string $expectedStatus,
    bool $expectsPlannedActual,
) {
    $admin = User::factory()->admin()->create();
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext();

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ])->fresh('exercises.sets.values');

    $slotExercise = $slot->exercises->first();

    if ($initialStatus === 'skipped') {
        $slotExercise->sets()->update([
            'completed_at' => null,
            'skipped_at' => now(),
        ]);
        app(TrainingSessionStatusService::class)->refreshExerciseState($slotExercise);
        $slotExercise->refresh();
    }

    if ($action === 'pending') {
        $config = $scheduledProgram->config;
        $config->setExerciseOverrides($pivot->id, ExerciseOverrides::from([
            'historicalGridOverrides' => [
                'cells' => [
                    ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 9]],
                ],
                'sessions' => [],
            ],
        ]), $athlete->id);
        $scheduledProgram->config = $config;
        $scheduledProgram->saveQuietly();
    }

    $slotExercise->sets->first()->values->first()->update([
        'actual_value_type' => 'int',
        'actual_int_value' => 0,
        'actual_recorded_by' => $athlete->id,
        'actual_recorded_at' => now(),
        'actual_source' => 'athlete',
    ]);

    $component = Livewire::actingAs($admin)->test(PlanExerciseGrid::class, [
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
    ])->call('requestRecordAction', 0, 0, $action);

    if ($action === 'pending') {
        $component
            ->assertSet('pendingRecordAction', null)
            ->assertSet('pendingRecordWeek', null)
            ->assertSet('pendingRecordSession', null);
    } else {
        $component
            ->assertSet('pendingRecordAction', $action)
            ->assertSet('pendingRecordWeek', 0)
            ->assertSet('pendingRecordSession', 0);

        expect($slotExercise->refresh()->status->value)->toBe($initialStatus);

        $component->call('confirmRecordAction');
    }

    $recordedValue = $slotExercise->sets->first()->values->first()->refresh();
    $batch = TrainingRevisionBatch::query()
        ->where('action', "mark_exercise_{$action}")
        ->latest('id')
        ->firstOrFail();
    $setRevision = TrainingStateRevision::query()
        ->where('batch_id', $batch->id)
        ->where('subject_type', TrainingProgramSlotSet::class)
        ->where('subject_id', $slotExercise->sets->first()->id)
        ->firstOrFail();

    expect($slotExercise->refresh()->status->value)->toBe($expectedStatus)
        ->and($component->get('sessionStatusesByWeek')[0][0]['value'])->toBe($expectedStatus)
        ->and($component->get('lockedSessionsByWeek')[0][0])->toBe($action !== 'pending')
        ->and($batch->changed_by)->toBe($admin->id)
        ->and($batch->source)->toBe('admin')
        ->and($setRevision->changed_by)->toBe($admin->id)
        ->and($setRevision->source)->toBe('admin')
        ->and($setRevision->before_payload['values'][0])->toHaveKey('planned_value_type')
        ->and($setRevision->before_payload['values'][0]['actual_int_value'])->toBe(0)
        ->and($setRevision->before_payload['values'][0]['actual_recorded_by'])->toBe($athlete->id);

    if (! $expectsPlannedActual) {
        expect($recordedValue->actual_value_type)->toBeNull()
            ->and($recordedValue->actual_int_value)->toBeNull()
            ->and($recordedValue->actual_recorded_by)->toBeNull()
            ->and($setRevision->after_payload['values'][0]['actual_value_type'])->toBeNull()
            ->and($setRevision->after_payload['values'][0]['actual_recorded_by'])->toBeNull();
    } else {
        expect($recordedValue->actual_value_type)->not->toBeNull()
            ->and(app(TrainingValueSnapshotCodec::class)->extractActualValue($recordedValue))->not->toBe(0)
            ->and($recordedValue->actual_recorded_by)->toBe($admin->id)
            ->and($recordedValue->actual_source)->toBe('admin')
            ->and($setRevision->after_payload['values'][0]['actual_recorded_by'])->toBe($admin->id)
            ->and($setRevision->after_payload['values'][0]['actual_source'])->toBe('admin');
    }

    if ($action === 'pending') {
        $savedOverrides = $scheduledProgram->fresh()->config->exerciseOverrides($pivot->id, $athlete->id);
        $historyBatch = TrainingRevisionBatch::query()
            ->where('action', 'mark_exercise_pending_archive_historical')
            ->latest('id')
            ->firstOrFail();
        $activeRepsOverride = collect($savedOverrides->gridOverrides['cells'] ?? [])
            ->first(fn (array $cell): bool => ($cell['week'] ?? null) === 0
                && ($cell['session'] ?? null) === 0
                && ($cell['set'] ?? null) === 0)['data']['reps'] ?? null;

        expect($savedOverrides->historicalGridOverrides['cells'])->toBe([])
            ->and($savedOverrides->historicalGridOverrides['sessions'])->toBe([])
            ->and($activeRepsOverride)->toBe(9)
            ->and($historyBatch->changed_by)->toBe($admin->id)
            ->and($historyBatch->source)->toBe('admin');

        $component->call('updatePlannedDisplayCellValue', 0, 0, 'reps', 15, 0);

        expect((string) app(TrainingValueSnapshotCodec::class)->extractPlannedValue($recordedValue->refresh()))->toBe('15');
    }
})->with([
    'skipped' => ['skipped', 'pending', 'skipped', false],
    'completed' => ['completed', 'pending', 'completed', true],
    'pending' => ['pending', 'skipped', 'pending', false],
]);

it('opens Edit immediately with existing values and without confirmation', function () {
    $coach = User::factory()->coach()->create();
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext();
    $trainingProgram->group()->update(['owner_id' => $coach->id]);

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ])->fresh('exercises.sets.values');

    $value = $slot->exercises->first()->sets->first()->values->first();
    $value->update([
        'actual_value_type' => 'int',
        'actual_int_value' => 0,
        'actual_recorded_by' => $athlete->id,
        'actual_recorded_at' => now(),
        'actual_source' => 'athlete',
    ]);

    Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
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
        'programExerciseType' => 'main',
    ])
        ->call('requestRecordAction', 0, 0, 'edit')
        ->assertSet('pendingRecordAction', null)
        ->assertDispatched(
            'open-program-record-at-session',
            sessionKey: (string) $slot->id,
            section: 'main',
            exerciseId: $exercise->id,
            exerciseSort: 0,
        );

    expect($value->refresh()->actual_int_value)->toBe(0)
        ->and($value->actual_recorded_by)->toBe($athlete->id);
});

it('shows record actions only for a selected athlete in plan and actual mode', function () {
    $groupOwner = User::factory()->coach()->create();
    $coach = User::factory()->coach()->create();
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext();
    $trainingProgram->group()->update(['owner_id' => $groupOwner->id]);

    TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ]);

    $parameters = [
        'planId' => $scheduledProgram->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'planned',
    ];

    $plannedAthleteGrid = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        ...$parameters,
        'userId' => $athlete->id,
    ]);
    $actualAthleteGrid = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        ...$parameters,
        'userId' => $athlete->id,
        'valueDisplayMode' => 'actual',
    ]);
    $groupGrid = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        ...$parameters,
        'userId' => null,
    ]);

    expect($plannedAthleteGrid->instance()->recordMenuOptions())->toBe([])
        ->and($actualAthleteGrid->instance()->recordMenuOptions())->not->toBeEmpty()
        ->and($groupGrid->instance()->recordMenuOptions())->toBe([]);

    $plannedAthleteGrid
        ->call('requestRecordAction', 0, 0, 'edit')
        ->assertStatus(403);
});

it('shows only valid status transitions and rejects completing an already completed exercise', function () {
    $coach = User::factory()->coach()->create();
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext();

    TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
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

    $component
        ->assertSee('Edit')
        ->assertSee('Mark as Skipped')
        ->assertSee('Mark as Completed')
        ->assertDontSee('Mark as Pending')
        ->call('requestRecordAction', 0, 0, 'completed')
        ->assertSee('Mark as Pending')
        ->assertDontSee('Mark as Completed')
        ->call('requestRecordAction', 0, 0, 'completed')
        ->assertStatus(422);
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
        'status' => TrainingProgramSlotExerciseStatusEnum::Completed,
        'completed_set_count' => 1,
        'pending_set_count' => 0,
    ])->save();

    $slotExercise->sets->first()->forceFill([
        'status' => TrainingProgramSlotSetStatusEnum::Completed,
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

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ])->fresh('exercises.sets.values');
    $slot->exercises->first()->sets->first()->values->first()->update([
        'actual_value_type' => 'decimal',
        'actual_decimal_value' => 10,
        'actual_is_explicit' => true,
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
    $coach = User::factory()->coach()->create();
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

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
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
        ->assertSee('Sets')
        ->assertSee('Mark as Skipped')
        ->assertSee('Mark as Completed');
});

it('keeps grouped three-set headers aligned when toggling planned and actual columns', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps', 'weight', 'tempo', 'rest'],
        'sets' => ['default' => 3, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
        'weight' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
        'tempo' => ['default' => '3010', 'applyPer' => 'week'],
        'rest' => ['default' => 60, 'applyPer' => 'week'],
        'preview' => [
            'groupingMode' => SessionGroupingMode::Groups->value,
            'groupSize' => 2,
            'copyValuesAutomatically' => true,
        ],
    ]);

    foreach (['2026-04-27', '2026-04-30'] as $date) {
        TrainingProgramSlot::create([
            'training_program_id' => $trainingProgram->id,
            'user_id' => $athlete->id,
            'datetime' => $date.' 09:00:00',
            'scheduled_date' => $date,
            'status' => TrainingProgramSlotStatusEnum::Completed,
            'completed_at' => $date.' 10:00:00',
        ]);
    }

    $parameters = [
        'planId' => $scheduledProgram->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2],
        'weekSessionDates' => [['2026-04-27', '2026-04-30']],
        'sessionStatusesByWeek' => [[
            [
                'value' => TrainingProgramSlotStatusEnum::Completed->value,
                'label' => 'Completed',
                'color' => ['light' => '110 231 183', 'dark' => '52 211 153'],
            ],
            [
                'value' => TrainingProgramSlotStatusEnum::Completed->value,
                'label' => 'Completed',
                'color' => ['light' => '110 231 183', 'dark' => '52 211 153'],
            ],
        ]],
        'showActualValueTabs' => true,
    ];

    $component = Livewire::test(PlanExerciseGrid::class, [
        ...$parameters,
        'valueDisplayMode' => 'planned',
    ]);

    $component->call('togglePlannedActualInline');

    $html = $component->html();
    $individualEditingGroup = $component->instance()->displayGrid()->groups[0];

    expect($html)->toMatch('/>\s*Set 1\s*</')
        ->and($html)->toMatch('/>\s*Set 2\s*</')
        ->and($html)->toMatch('/>\s*Set 3\s*</')
        ->and(preg_match_all('/>\s*Planned\s*</', $html))->toBe(3)
        ->and(preg_match_all('/>\s*Actual\s*</', $html))->toBe(3)
        ->and(substr_count($html, 'style="width: 5rem; min-width: 5rem; max-width: 5rem;"'))->toBeGreaterThanOrEqual(12)
        ->and($html)->toMatch('/>\s*Tempo\s*</')
        ->and($html)->toMatch('/>\s*Rest \(s\)\s*</')
        ->and($html)->toMatch('/>\s*Sets\s*</')
        ->and($individualEditingGroup->forceExpanded)->toBeTrue()
        ->and($individualEditingGroup->expanded)->toBeTrue()
        ->and($individualEditingGroup->collapsible)->toBeFalse();

    $programEditingComponent = Livewire::test(PlanExerciseGrid::class, [
        ...$parameters,
        'valueDisplayMode' => 'actual',
    ]);
    $programEditingGroup = $programEditingComponent->instance()->displayGrid()->groups[0];

    expect($programEditingGroup->forceExpanded)->toBeTrue()
        ->and($programEditingGroup->expanded)->toBeTrue()
        ->and($programEditingGroup->collapsible)->toBeFalse();
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
        'actual_value_type' => 'int',
        'actual_int_value' => 8,
        'actual_is_explicit' => true,
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

it('does not highlight a frozen planned snapshot when it matches the underlying plan', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 7, 'applyPer' => 'session'],
    ]);

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ])->fresh('exercises.sets.values');

    $value = $slot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'reps');
    $value->update([
        'actual_value_type' => 'int',
        'actual_int_value' => 7,
        'actual_is_explicit' => true,
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
        'lockedSessionsByWeek' => [[true]],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'planned',
    ]);

    $row = collect($component->instance()->displayGrid()->rows)->firstWhere('field', 'reps');
    $cell = $row->presentCell(0, 0, 0, locked: true);

    expect($cell['value'])->toEqual(7)
        ->and($cell['overridden'])->toBeFalse()
        ->and($cell['color'])->toBe($row->color);
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
        $slot = TrainingProgramSlot::create([
            'training_program_id' => $trainingProgram->id,
            'user_id' => $athlete->id,
            'datetime' => $dateTime,
            'scheduled_date' => substr($dateTime, 0, 10),
        ])->fresh('exercises.sets.values');
        $slot->exercises->first()->sets->first()->values->first()->update([
            'actual_value_type' => 'decimal',
            'actual_decimal_value' => 10,
            'actual_is_explicit' => true,
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
