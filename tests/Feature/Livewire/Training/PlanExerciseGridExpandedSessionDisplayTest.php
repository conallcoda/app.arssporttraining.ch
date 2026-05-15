<?php

use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Users\User;
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

    $component->call('toggleExpandedGroup', 0)
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

it('hides the grouped column when disabled in coach settings', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => true,
        'showGroupedColumn' => false,
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

it('refreshes an open plan grid after coach settings change', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => true,
        'showGroupedColumn' => true,
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

    expect($component->instance()->displayGrid->renderGroupColumn)->toBeTrue();

    $coach->config->set('settings.session_grouping.showGroupedColumn', false);
    $coach->save();

    $component->call('onCoachSettingsSaved');

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
