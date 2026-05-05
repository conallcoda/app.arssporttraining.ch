<?php

use App\Livewire\Training\View\PlanExerciseGrid;
use App\Livewire\Training\View\ProgramEditor;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
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
        'exercisePlanId' => $scheduledProgram->id,
        'planType' => ExerciseProgram::class,
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
        'exercisePlanId' => $scheduledProgram->id,
        'planType' => ExerciseProgram::class,
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
