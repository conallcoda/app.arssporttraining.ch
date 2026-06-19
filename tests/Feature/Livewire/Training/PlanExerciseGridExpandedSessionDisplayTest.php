<?php

use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows session-specific values for expanded mixed past and future weeks', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
        ],
        'lockedSessionsByWeek' => [
            [true, false],
        ],
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 14, 1, false);

    $grid = $component->instance()->previewGrid;
    $row = collect($grid->rows)->firstWhere('field', 'reps');

    expect($row)->not->toBeNull()
        ->and($row->getCellValue(0, 0, 0))->toBe(12)
        ->and($row->getCellValue(0, 0, 1))->toBe(14)
        ->and($row->isCellOverriddenAt(0, 0, 0))->toBeFalse()
        ->and($row->isCellOverriddenAt(0, 0, 1))->toBeTrue();
});

it('does not show a bilateral reps hint in the plan exercise grid header', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => [
                'mode' => 'manual',
                'default' => '7_9',
                'bilateralExecution' => 'alternating',
                'applyPer' => 'session',
            ],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [
            ['2026-04-27'],
        ],
    ])
        ->assertDontSee('Alternate sides each rep')
        ->assertDontSee('Complete all reps on one side first')
        ->assertSee('7L_9R');
});

it('shows centralized session status chips in the planner session column', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
        ],
        'expandedWeeks' => [0],
        'sessionStatusesByWeek' => [
            [
                [
                    'value' => TrainingProgramSlotStatusEnum::Pending->value,
                    'label' => 'Pending',
                    'color' => ['light' => '228 228 231', 'dark' => '161 161 170'],
                ],
                [
                    'value' => TrainingProgramSlotStatusEnum::PartiallyCompleted->value,
                    'label' => 'Partially Completed',
                    'color' => ['light' => '252 211 77', 'dark' => '245 158 11'],
                ],
            ],
        ],
    ])
        ->assertSee('Pending')
        ->assertSee('Partially Completed')
        ->assertSee('status-badge', false);
});

it('shows historical sessions even when they match the other sessions', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
        ],
        'lockedSessionsByWeek' => [
            [true, false],
        ],
        'expandedWeeks' => [],
    ]);

    expect($component->instance()->effectiveExpandedWeeks)->toBe([0])
        ->and($component->instance()->displayGrid->weeks[0]->expanded)->toBeTrue();
});

it('auto-expands a future week when sessions diverge', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2],
        'weekSessionDates' => [
            ['2026-05-01', '2026-05-02'],
        ],
        'lockedSessionsByWeek' => [
            [false, false],
        ],
        'expandedWeeks' => [],
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 14, 1, false);

    expect($component->instance()->effectiveExpandedWeeks)->toBe([0]);
});

it('auto-expands a future week when only session 0 has an explicit override', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2],
        'weekSessionDates' => [
            ['2026-05-01', '2026-05-02'],
        ],
        'lockedSessionsByWeek' => [
            [false, false],
        ],
        'expandedWeeks' => [],
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 14, 0, false);

    $grid = $component->instance()->previewGrid;
    $row = collect($grid->rows)->firstWhere('field', 'reps');

    expect($row)->not->toBeNull()
        ->and($row->getCellValue(0, 0, 0))->toBe(14)
        ->and($row->getCellValue(0, 0, 1))->toBe(12)
        ->and($row->isCellOverriddenAt(0, 0, 1))->toBeFalse()
        ->and($component->instance()->effectiveExpandedWeeks)->toBe([0]);
});

it('shows session-specific week-column values for expanded mixed past and future weeks', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['rest'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'rest' => ['default' => 60, 'applyPer' => 'week'],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
        ],
        'lockedSessionsByWeek' => [
            [true, false],
        ],
    ]);

    $component->call('updateSessionOverride', 0, 1, 'rest', 90);

    $grid = $component->instance()->previewGrid;
    $column = collect($grid->weekColumns)->firstWhere('field', 'rest');

    expect($column)->not->toBeNull()
        ->and($column->getCellValue(0, 0, 0))->toBe(60)
        ->and($column->getCellValue(0, 0, 1))->toBe(90)
        ->and($column->isCellOverriddenAt(0, 0, 0))->toBeFalse()
        ->and($column->isCellOverriddenAt(0, 0, 1))->toBeTrue();
});

it('shows the week grouping column even when week mode has a single visible bucket', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => SessionGroupingMode::Week->value,
        'groupSize' => 1,
        'copyValuesAutomatically' => true,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekLabels' => ['W1'],
        'weekSessions' => [2],
    ]);

    $displayGrid = $component->instance()->displayGrid;

    expect($displayGrid->showGroupColumn)->toBeTrue()
        ->and($displayGrid->groupColumnLabel)->toBe('Week')
        ->and($displayGrid->groups)->toHaveCount(1)
        ->and($displayGrid->groups[0]->label)->toBe('W1');
});

it('auto-expands a mixed week when only a week-wide field differs between past and future sessions', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['rest'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'rest' => ['default' => 60, 'applyPer' => 'week'],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
        ],
        'lockedSessionsByWeek' => [
            [true, false],
        ],
        'expandedWeeks' => [],
    ]);

    $component->call('updateSessionOverride', 0, 1, 'rest', 90);

    expect($component->instance()->effectiveExpandedWeeks)->toBe([0]);
});

it('applies collapsed session edits across the whole visible group', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'week',
        'groupSize' => 1,
        'copyValuesAutomatically' => false,
    ]);
    $coach->save();

    $program = ExerciseProgram::factory()->create();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['rest'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'rest' => ['default' => 60, 'applyPer' => 'week'],
        ],
    ]);

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2],
        'expandedWeeks' => [],
    ]);

    $component->call('updateSessionOverride', 0, 0, 'rest', 90, true);

    $grid = $component->instance()->previewGrid;
    $column = collect($grid->weekColumns)->firstWhere('field', 'rest');

    expect($component->instance()->effectiveExpandedWeeks)->toBe([])
        ->and($column)->not->toBeNull()
        ->and($column->getCellValue(0, 0, 0))->toBe(90)
        ->and($column->getCellValue(0, 0, 1))->toBe(90)
        ->and($column->isCellOverriddenAt(0, 0, 0))->toBeTrue()
        ->and($column->isCellOverriddenAt(0, 0, 1))->toBeTrue();
});

it('stops fanning out once a group is manually expanded', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'week',
        'groupSize' => 1,
        'copyValuesAutomatically' => false,
    ]);
    $coach->save();

    $program = ExerciseProgram::factory()->create();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['rest'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'rest' => ['default' => 60, 'applyPer' => 'week'],
        ],
    ]);

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2],
        'expandedWeeks' => [],
    ]);

    $component->assertSee('Expand group');

    $component->call('toggleExpandedGroup', 0)
        ->assertSee('Collapse group')
        ->call('updateSessionOverride', 0, 0, 'rest', 75, false);

    $grid = $component->instance()->previewGrid;
    $column = collect($grid->weekColumns)->firstWhere('field', 'rest');

    expect($component->instance()->effectiveExpandedWeeks)->toBe([0])
        ->and($column)->not->toBeNull()
        ->and($column->getCellValue(0, 0, 0))->toBe(75)
        ->and($column->getCellValue(0, 0, 1))->toBe(60)
        ->and($column->isCellOverriddenAt(0, 0, 0))->toBeTrue()
        ->and($column->isCellOverriddenAt(0, 0, 1))->toBeFalse();
});

it('can group displayed sessions into fixed-size buckets across week boundaries', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 3,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => [
                'weeks' => 2,
                'sessionsPerWeek' => 2,
            ],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 2,
        'sessionsPerWeek' => 2,
    ]);

    $displayGrid = $component->instance()->displayGrid;

    expect($displayGrid->groupColumnLabel)->toBe('Group')
        ->and($displayGrid->groups)->toHaveCount(2)
        ->and($displayGrid->groups[0]->label)->toBe('G1')
        ->and(array_map(fn ($session) => [$session->weekIndex, $session->sessionIndex], $displayGrid->groups[0]->sessions))
            ->toBe([[0, 0], [0, 1], [0, 2]])
        ->and(array_map(fn ($session) => [$session->weekIndex, $session->sessionIndex], $displayGrid->groups[1]->sessions))
            ->toBe([[1, 0], [1, 1], [1, 2]]);
});

it('uses coach grouping defaults for existing plan grids when preview grouping is not persisted', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 3, 'sessionsPerWeek' => 2],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 3,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2, 1, 2],
    ]);

    $displayGrid = $component->instance()->displayGrid;

    expect($displayGrid->groupColumnLabel)->toBe('Group')
        ->and($displayGrid->groups)->toHaveCount(3)
        ->and(array_map(fn ($session) => [$session->weekIndex, $session->sessionIndex], $displayGrid->groups[0]->sessions))
            ->toBe([[0, 0], [0, 1]])
        ->and(array_map(fn ($session) => [$session->weekIndex, $session->sessionIndex], $displayGrid->groups[1]->sessions))
            ->toBe([[1, 0], [2, 0]])
        ->and(array_map(fn ($session) => [$session->weekIndex, $session->sessionIndex], $displayGrid->groups[2]->sessions))
            ->toBe([[2, 1]]);
});

it('treats planned groups as fixed-size session buckets in grouped plan grids', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 5],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 5,
        'sessionsPerWeek' => 1,
    ]);

    $displayGrid = $component->instance()->displayGrid;

    expect($component->instance()->previewGrid->weekSessionCounts)->toBe([2, 2, 2, 2, 2])
        ->and($displayGrid->groupColumnLabel)->toBe('Group')
        ->and($displayGrid->groups)->toHaveCount(5)
        ->and(array_map(fn ($session) => [$session->weekIndex, $session->sessionIndex], $displayGrid->groups[4]->sessions))
            ->toBe([[4, 0], [4, 1]]);
});

it('hides the group column when coach session grouping is none', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'none',
        'groupSize' => 1,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 2, 'sessionsPerWeek' => 2],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 2,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2, 1],
    ]);

    $displayGrid = $component->instance()->displayGrid;

    expect($displayGrid->showGroupColumn)->toBeFalse()
        ->and($displayGrid->groups)->toHaveCount(3)
        ->and(array_map(fn ($session) => [$session->weekIndex, $session->sessionIndex], $displayGrid->groups[0]->sessions))
            ->toBe([[0, 0]])
        ->and(array_map(fn ($session) => [$session->weekIndex, $session->sessionIndex], $displayGrid->groups[2]->sessions))
            ->toBe([[1, 0]]);
});

it('shows session dates in the plan grid when enabled in coach settings', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => false,
        'showDatePerSession' => true,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 1, 'sessionsPerWeek' => 2],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
        ],
    ]);

    expect($component->instance()->displayGrid->showSessionDates)->toBeTrue()
        ->and($component->instance()->displayGrid->sessionDateLabels)
            ->toBe([
                ['27.04.26', '30.04.26'],
            ])
        ->and($component->instance()->displayGrid->groups[0]->sessionRangeLabel)
            ->toBe('1-2')
        ->and($component->instance()->displayGrid->groups[0]->collapsedMetaLines)
            ->toBe([
                '27.04.26',
                '30.04.26',
            ]);
});

it('always hides the grouped column while keeping grouped behavior', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => true,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 2, 'sessionsPerWeek' => 2],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 2,
        'sessionsPerWeek' => 2,
    ]);

    expect($component->instance()->displayGrid->showGroupColumn)->toBeTrue()
        ->and($component->instance()->displayGrid->renderGroupColumn)->toBeFalse();
});

it('keeps grouped preview buckets and hides reset for locked groups without rendering a grouped column', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => true,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 2, 'sessionsPerWeek' => 2],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 2,
        'sessionsPerWeek' => 2,
        'lockedSessionsByWeek' => [
            [true, true],
            [false, false],
        ],
    ]);

    expect($component->instance()->displayGrid->renderGroupColumn)->toBeFalse()
        ->and(array_keys($component->get('previewMenuOptions')))->toContain('group:0', 'group:1')
        ->and($component->get('previewMenuOptions')['group:0'] ?? [])->toHaveCount(2)
        ->and($component->get('resetMenuOptions')['group:0'] ?? null)->toBeFalse()
        ->and($component->get('resetMenuOptions')['group:1'] ?? null)->toBeTrue()
        ->and($component->html())->not->toContain("resetDisplayBucket('group:0')");
});

it('shows reset for unrecorded past grouped sessions when lock metadata is open', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => true,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 2, 'sessionsPerWeek' => 2],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();
    $scheduledProgram = TrainingProgram::factory()->create([
        'group_id' => UserGroup::create(['name' => 'Test Group'])->id,
        'exercise_program_id' => $program->id,
    ]);

    $pastDates = [now()->subDays(10)->toDateString(), now()->subDays(8)->toDateString()];
    $futureDates = [now()->addDays(10)->toDateString(), now()->addDays(12)->toDateString()];

    foreach ([...$pastDates, ...$futureDates] as $date) {
        TrainingProgramSlot::factory()->create([
            'training_program_id' => $scheduledProgram->id,
            'user_id' => $athlete->id,
            'datetime' => $date.' 09:00:00',
            'scheduled_date' => $date,
        ]);
    }

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'scheduledTrainingProgramId' => $scheduledProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 2,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            $pastDates,
            $futureDates,
        ],
        'lockedSessionsByWeek' => [
            [false, false],
            [false, false],
        ],
    ]);

    expect($component->instance()->displayGrid->renderGroupColumn)->toBeFalse()
        ->and($component->get('previewMenuOptions')['group:0'] ?? [])->toHaveCount(2)
        ->and($component->get('previewMenuOptions')['group:1'] ?? [])->toHaveCount(2)
        ->and($component->get('resetMenuOptions')['group:0'] ?? null)->toBeTrue()
        ->and($component->get('resetMenuOptions')['group:1'] ?? null)->toBeTrue()
        ->and($component->html())->toContain("resetDisplayBucket('group:0')");
});

it('keeps the grouped column hidden when grouping is enabled', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => true,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 2, 'sessionsPerWeek' => 2],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 2,
        'sessionsPerWeek' => 2,
    ]);

    expect($component->instance()->displayGrid->renderGroupColumn)->toBeFalse();
});

it('groups weeks into configurable multi-week buckets in week mode', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'week',
        'groupSize' => 2,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 4, 'sessionsPerWeek' => 2],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 4,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2, 2, 2, 2],
    ]);

    $displayGrid = $component->instance()->displayGrid;

    expect($displayGrid->groups)->toHaveCount(2)
        ->and($displayGrid->groups[0]->label)->toBe('W1-W2')
        ->and($displayGrid->groups[0]->sessionRangeLabel)->toBe('1-4')
        ->and($displayGrid->groups[1]->label)->toBe('W3-W4')
        ->and($displayGrid->groups[1]->sessionRangeLabel)->toBe('5-8');
});

it('renders collapsed grouped cells as editable and applies to the whole bucket by default', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 2, 'sessionsPerWeek' => 2],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 2,
        'sessionsPerWeek' => 2,
    ])->assertSeeHtml('data-apply-to-all="true"');
});

it('keeps identical grouped sessions collapsed when the grid includes a last-session-only row', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps', 'weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'weight' => ['mode' => 'automatic', 'default' => 75, 'applyPer' => 'session'],
            'preview' => ['weeks' => 2, 'sessionsPerWeek' => 2],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 2,
        'sessionsPerWeek' => 2,
    ]);

    expect($component->instance()->effectiveExpandedWeeks)->toBe([]);
});
