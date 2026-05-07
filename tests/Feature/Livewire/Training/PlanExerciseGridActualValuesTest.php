<?php

use App\Livewire\Training\View\PlanExerciseGrid;
use App\Livewire\Training\View\ProgramEditor;
use App\Data\Training\Config\ExerciseOverrides;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingActualValueRevision;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionStatusService;
use App\Training\TrainingValueSnapshotCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('does not show the mode toggle on the regular program editor', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'showWeeksInput' => true,
    ])
        ->assertSet('showActualValueTabs', false)
        ->assertSet('valueDisplayMode', 'planned');
});

it('shows the mode toggle for scheduled program grids with a selected athlete', function () {
    [$athlete, $scheduledProgram, $pivot] = buildScheduledProgramContext();

    $component = Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $scheduledProgram,
        'planId' => $scheduledProgram->id,
        'userId' => $athlete->id,
        'showWeeksInput' => false,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
    ]);

    $component
        ->assertSee('Display')
        ->assertSee('Plan')
        ->assertSee('Plan + Actual');
});

it('does not show the display toggle for scheduled program grids without a selected athlete', function () {
    [, $scheduledProgram] = buildScheduledProgramContext();

    Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $scheduledProgram,
        'planId' => $scheduledProgram->id,
        'userId' => null,
        'showWeeksInput' => false,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
    ])
        ->assertDontSee('Plan + Actual');
});

it('does not show per-exercise mode tabs inside the exercise grid', function () {
    [$athlete, $scheduledProgram, $pivot] = buildScheduledProgramContext();

    Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $pivot->exercise_id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
    ])
        ->assertDontSee('Display')
        ->assertDontSee('Plan')
        ->assertDontSee('Plan + Actual');
});

it('maps athlete actual values onto the scheduled calendar grid', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps', 'rest', 'weight'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        'weight' => ['mode' => 'manual', 'default' => 47.5, 'applyPer' => 'session'],
        'rest' => ['default' => 60, 'applyPer' => 'week'],
    ]);

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ]);

    $slotExercise = TrainingProgramSlotExercise::create([
        'training_program_slot_id' => $slot->id,
        'exercise_id' => $exercise->id,
        'sort' => $pivot->sort,
        'group' => $pivot->group,
        'type' => $pivot->type,
        'set_count' => 1,
        'completed_set_count' => 0,
        'modified_set_count' => 0,
        'skipped_set_count' => 0,
        'pending_set_count' => 1,
        'has_any_modification' => true,
    ]);

    $set = TrainingProgramSlotSet::create([
        'training_program_slot_exercise_id' => $slotExercise->id,
        'set_number' => 1,
        'has_any_modification' => true,
    ]);

    $set->values()->create([
        'setting_key' => 'reps',
        'planned_value_type' => 'int',
        'planned_int_value' => 12,
        'actual_value_type' => 'int',
        'actual_int_value' => 14,
        'unit' => null,
        'is_modified' => true,
    ]);

    $set->values()->create([
        'setting_key' => 'weight',
        'planned_value_type' => 'decimal',
        'planned_decimal_value' => 47.5,
        'actual_value_type' => null,
        'unit' => 'kg',
        'is_modified' => false,
    ]);

    $set->values()->create([
        'setting_key' => 'rest',
        'planned_value_type' => 'int',
        'planned_int_value' => 60,
        'actual_value_type' => 'int',
        'actual_int_value' => 75,
        'unit' => 's',
        'is_modified' => true,
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
    ]);

    $component
        ->assertSee('14')
        ->assertSee('75')
        ->assertSee('47.5');
});

it('lets coaches record actual cell values for locked historical sessions in actual mode', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['reps'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
    ]);

    $coach = User::factory()->coach()->create();

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ])->fresh('exercises.sets.values');

    $slotExercise = $slot->exercises->first();
    $set = $slotExercise->sets->first();
    $value = $set->values->firstWhere('setting_key', 'reps');

    Livewire::actingAs($coach)
        ->test(PlanExerciseGrid::class, [
            'planId' => $scheduledProgram->id,
            'programExerciseId' => $pivot->id,
            'exerciseId' => $pivot->exercise_id,
            'userId' => $athlete->id,
            'weeks' => 1,
            'sessionsPerWeek' => 1,
            'weekSessions' => [1],
            'weekSessionDates' => [['2026-04-27']],
            'lockedSessionsByWeek' => [[true]],
            'showActualValueTabs' => true,
            'valueDisplayMode' => 'actual',
        ])
        ->call('updateActualCellValue', 0, 0, 'reps', '14', 0);

    $value = $value->fresh();

    expect($value->actual_value_type)->toBe('string')
        ->and($value->actual_string_value)->toBe('14')
        ->and($value->actual_recorded_by)->toBe($coach->id)
        ->and($value->actual_source)->toBe('coach')
        ->and($value->actual_is_explicit)->toBeTrue()
        ->and($value->is_modified)->toBeTrue()
        ->and(TrainingRevisionBatch::query()->latest('id')->first()?->domain)->toBe('actual')
        ->and(TrainingActualValueRevision::query()->latest('id')->first()?->training_program_slot_set_value_id)->toBe($value->id);
});

it('lets coaches record session actual values across all sets for locked historical sessions in actual mode', function () {
    [$athlete, $scheduledProgram, $pivot, $exercise, $trainingProgram] = buildScheduledProgramContext([
        'settings' => ['rest'],
        'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
        'rest' => ['default' => 60, 'applyPer' => 'session'],
    ]);

    $coach = User::factory()->coach()->create();

    $slot = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-04-27 09:00:00',
        'scheduled_date' => '2026-04-27',
    ])->fresh('exercises.sets.values');

    $slotExercise = $slot->exercises->first();

    Livewire::actingAs($coach)
        ->test(PlanExerciseGrid::class, [
            'planId' => $scheduledProgram->id,
            'programExerciseId' => $pivot->id,
            'exerciseId' => $pivot->exercise_id,
            'userId' => $athlete->id,
            'weeks' => 1,
            'sessionsPerWeek' => 1,
            'weekSessions' => [1],
            'weekSessionDates' => [['2026-04-27']],
            'lockedSessionsByWeek' => [[true]],
            'showActualValueTabs' => true,
            'valueDisplayMode' => 'actual',
        ])
        ->call('updateActualSessionValue', 0, 0, 'rest', 75);

    $restValues = $slotExercise->fresh('sets.values')->sets
        ->flatMap->values
        ->where('setting_key', 'rest')
        ->values();

    expect($restValues)->toHaveCount(2)
        ->and($restValues->pluck('actual_int_value')->all())->toBe([75, 75])
        ->and($restValues->every(fn ($value): bool => $value->actual_recorded_by === $coach->id))->toBeTrue()
        ->and($restValues->every(fn ($value): bool => $value->is_modified))->toBeTrue();
});

it('groups split actual set headers under a single set label', function () {
    [$athlete, $scheduledProgram, $pivot] = buildScheduledProgramContext([
        'settings' => ['reps'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'cell'],
    ]);

    Livewire::test(PlanExerciseGrid::class, [
        'planId' => $scheduledProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $pivot->exercise_id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-04-27']],
        'showActualValueTabs' => true,
        'valueDisplayMode' => 'actual',
    ])
        ->assertSeeHtml('colspan="2"')
        ->assertDontSee('Planned')
        ->assertDontSee('Actual');
});

it('syncs locked historical planned snapshot values through the central refresh path when the grid is saved', function () {
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
        'actual_string_value' => $value->planned_value_type === 'string' ? '5' : null,
        'actual_int_value' => $value->planned_value_type === 'int' ? 5 : null,
        'actual_decimal_value' => $value->planned_value_type === 'decimal' ? 5.0 : null,
        'actual_json_value' => $value->planned_value_type === 'json' ? 5 : null,
        'actual_is_explicit' => true,
    ]);
    $slotSet->update([
        'completed_at' => now(),
    ]);

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

    $slot = $slot->fresh('exercises.sets.values');
    $slotExercise = $slot->exercises->first();
    $slotSet = $slotExercise->sets->first();
    $value = $slotSet->values->firstWhere('setting_key', 'reps');

    expect((string) app(TrainingValueSnapshotCodec::class)->extractPlannedValue($value))->toBe('5')
        ->and($value->is_modified)->toBeFalse()
        ->and($slotSet->status)->toBe(TrainingProgramSlotSetStatusEnum::Completed)
        ->and($slotExercise->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Completed)
        ->and($slotExercise->modified_set_count)->toBe(0)
        ->and($slotExercise->has_any_modification)->toBeFalse()
        ->and($slot->has_any_modification)->toBeFalse();
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
