<?php

use App\Livewire\Training\CalendarExerciseSettingsForm;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('stores scheduled preview edits only on the targeted session', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 2, 'sessionsPerWeek' => 2],
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(CalendarExerciseSettingsForm::class);

    $component->call('openForExercise', [
        'exerciseId' => $exercise->id,
        'exerciseProgramId' => $program->id,
        'weeks' => 2,
        'sessionsPerWeek' => 2,
        'weekLabels' => [],
        'weekSessions' => [2, 1],
        'scheduled' => true,
        'config' => $exercise->config->toArray(),
    ]);

    $component->call('updateCellOverride', 1, 0, 'reps', '13_13', 0, true);

    $cells = $component->get('data')['config']['overrides']['cells'] ?? [];

    expect(collect($cells)->where('week', 1)->where('session', 0)->first()['data']['reps'] ?? null)
        ->toBe('13_13')
        ->and(collect($cells)->where('week', 1)->where('session', 1)->first())
        ->toBeNull();
});

it('auto-expands a scheduled preview week when sessions diverge', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 1, 'sessionsPerWeek' => 2],
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(CalendarExerciseSettingsForm::class);

    $component->call('openForExercise', [
        'exerciseId' => $exercise->id,
        'exerciseProgramId' => $program->id,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekLabels' => [],
        'weekSessions' => [2],
        'scheduled' => true,
        'config' => $exercise->config->toArray(),
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 14, 1, false);

    expect($component->instance()->effectiveExpandedWeeks)->toBe([0]);
});

it('uses coach grouping defaults for scheduled previews when grouping is not persisted', function () {
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
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(CalendarExerciseSettingsForm::class);

    $component->call('openForExercise', [
        'exerciseId' => $exercise->id,
        'exerciseProgramId' => $program->id,
        'weeks' => 3,
        'sessionsPerWeek' => 2,
        'weekLabels' => [],
        'weekSessions' => [2, 1, 2],
        'scheduled' => true,
        'config' => $exercise->config->toArray(),
    ]);

    $grid = $component->instance()->previewGrid;

    expect($grid->groupColumnLabel)->toBe('Group')
        ->and($grid->groups)->toHaveCount(3)
        ->and(array_map(fn ($session) => [$session->weekIndex, $session->sessionIndex], $grid->groups[1]->sessions))
            ->toBe([[1, 0], [2, 0]]);
});

it('defaults scheduled previews to groups of two when coach grouping is unset', function () {
    $coach = User::factory()->coach()->create();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 3, 'sessionsPerWeek' => 2],
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(CalendarExerciseSettingsForm::class);

    $component->call('openForExercise', [
        'exerciseId' => $exercise->id,
        'exerciseProgramId' => $program->id,
        'weeks' => 3,
        'sessionsPerWeek' => 2,
        'weekLabels' => [],
        'weekSessions' => [2, 1, 2],
        'scheduled' => true,
        'config' => $exercise->config->toArray(),
    ]);

    $grid = $component->instance()->previewGrid;

    expect($grid->groupColumnLabel)->toBe('Group')
        ->and($grid->groups)->toHaveCount(3)
        ->and(array_map(fn ($session) => [$session->weekIndex, $session->sessionIndex], $grid->groups[1]->sessions))
            ->toBe([[1, 0], [2, 0]]);
});

it('uses grouped deload defaults when saving scheduled set overrides', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => [],
            'sets' => ['default' => 4, 'label' => 'Set', 'deload' => 'odd', 'deloadBy' => 1],
            'preview' => ['weeks' => 3, 'sessionsPerWeek' => 2],
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(CalendarExerciseSettingsForm::class);

    $component->call('openForExercise', [
        'exerciseId' => $exercise->id,
        'exerciseProgramId' => $program->id,
        'weeks' => 3,
        'sessionsPerWeek' => 2,
        'weekLabels' => [],
        'weekSessions' => [2, 1, 2],
        'scheduled' => true,
        'config' => $exercise->config->toArray(),
    ]);

    $component->call('updateSessionOverride', 2, 0, 'sets', 4);

    expect($component->get('data')['config']['overrides']['sessions'] ?? [])
        ->toBe([]);
});

it('keeps identical scheduled preview groups collapsed by default', function () {
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
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(CalendarExerciseSettingsForm::class);

    $component->call('openForExercise', [
        'exerciseId' => $exercise->id,
        'exerciseProgramId' => $program->id,
        'weeks' => 3,
        'sessionsPerWeek' => 2,
        'weekLabels' => [],
        'weekSessions' => [2, 2, 2],
        'scheduled' => true,
        'config' => $exercise->config->toArray(),
    ]);

    expect($component->instance()->effectiveExpandedWeeks)->toBe([]);
});

it('keeps identical scheduled preview groups collapsed when the grid includes a last-session-only row', function () {
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
            'preview' => ['weeks' => 3, 'sessionsPerWeek' => 2],
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::actingAs($coach)->test(CalendarExerciseSettingsForm::class);

    $component->call('openForExercise', [
        'exerciseId' => $exercise->id,
        'exerciseProgramId' => $program->id,
        'weeks' => 3,
        'sessionsPerWeek' => 2,
        'weekLabels' => [],
        'weekSessions' => [2, 2, 2],
        'scheduled' => true,
        'config' => $exercise->config->toArray(),
    ]);

    expect($component->instance()->effectiveExpandedWeeks)->toBe([]);
});

it('copies session buckets in scheduled previews when grouping is none', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 2, 'sessionsPerWeek' => 1, 'groupingMode' => 'none', 'groupSize' => 1],
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(CalendarExerciseSettingsForm::class);

    $component->call('openForExercise', [
        'exerciseId' => $exercise->id,
        'exerciseProgramId' => $program->id,
        'weeks' => 2,
        'sessionsPerWeek' => 1,
        'weekLabels' => [],
        'weekSessions' => [1, 1],
        'scheduled' => true,
        'config' => $exercise->config->toArray(),
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 15, 0, false)
        ->call('copyDisplayBucket', 'session:0:0', 'session:1:0');

    $cells = $component->get('data')['config']['overrides']['cells'] ?? [];

    expect(collect($cells)->where('week', 1)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(15);
});

it('copies visible sessions in scheduled previews when grouping is week', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 2, 'sessionsPerWeek' => 2, 'groupingMode' => 'week', 'groupSize' => 2],
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(CalendarExerciseSettingsForm::class);

    $component->call('openForExercise', [
        'exerciseId' => $exercise->id,
        'exerciseProgramId' => $program->id,
        'weeks' => 2,
        'sessionsPerWeek' => 2,
        'weekLabels' => [],
        'weekSessions' => [2, 2],
        'scheduled' => true,
        'config' => $exercise->config->toArray(),
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 18, 0, true)
        ->call('copyDisplayBucket', 'group:0', 'group:1');

    $cells = $component->get('data')['config']['overrides']['cells'] ?? [];

    expect(collect($cells)->where('week', 1)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(18)
        ->and(collect($cells)->where('week', 1)->where('session', 1)->first()['data']['reps'] ?? null)->toBe(18);
});

it('applies collapsed scheduled session edits across the whole visible group', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['rest'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'rest' => ['default' => 60, 'applyPer' => 'week'],
            'preview' => ['weeks' => 1, 'sessionsPerWeek' => 2, 'groupingMode' => 'week', 'groupSize' => 1],
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(CalendarExerciseSettingsForm::class);

    $component->call('openForExercise', [
        'exerciseId' => $exercise->id,
        'exerciseProgramId' => $program->id,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekLabels' => [],
        'weekSessions' => [2],
        'scheduled' => true,
        'config' => $exercise->config->toArray(),
    ]);

    $component->call('updateSessionOverride', 0, 0, 'rest', 90, true);

    $sessions = $component->get('data')['config']['overrides']['sessions'] ?? [];

    expect($component->instance()->effectiveExpandedWeeks)->toBe([])
        ->and(collect($sessions)->where('week', 0)->where('session', 0)->first()['data']['rest'] ?? null)->toBe(90)
        ->and(collect($sessions)->where('week', 0)->where('session', 1)->first()['data']['rest'] ?? null)->toBe(90);
});

it('copies visible sessions in scheduled previews when grouping is groups', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 4, 'sessionsPerWeek' => 1, 'groupingMode' => 'groups', 'groupSize' => 2],
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(CalendarExerciseSettingsForm::class);

    $component->call('openForExercise', [
        'exerciseId' => $exercise->id,
        'exerciseProgramId' => $program->id,
        'weeks' => 4,
        'sessionsPerWeek' => 1,
        'weekLabels' => [],
        'weekSessions' => [1, 1, 1, 1],
        'scheduled' => true,
        'config' => $exercise->config->toArray(),
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 17, 0, true)
        ->call('copyDisplayBucket', 'group:0', 'group:1');

    $cells = $component->get('data')['config']['overrides']['cells'] ?? [];

    expect(collect($cells)->where('week', 2)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(17)
        ->and(collect($cells)->where('week', 3)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(17);
});

it('copies a visible scheduled group to all other groups', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 6, 'sessionsPerWeek' => 1, 'groupingMode' => 'groups', 'groupSize' => 2],
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(CalendarExerciseSettingsForm::class);

    $component->call('openForExercise', [
        'exerciseId' => $exercise->id,
        'exerciseProgramId' => $program->id,
        'weeks' => 6,
        'sessionsPerWeek' => 1,
        'weekLabels' => [],
        'weekSessions' => [1, 1, 1, 1, 1, 1],
        'scheduled' => true,
        'config' => $exercise->config->toArray(),
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 20, 0, true)
        ->call('copyDisplayBucketToAll', 'group:0');

    $copyMenuOptions = $component->get('copyMenuOptions');
    $cells = $component->get('data')['config']['overrides']['cells'] ?? [];

    expect($copyMenuOptions['group:0']['toAll']['label'] ?? null)->toBe('All')
        ->and(collect($copyMenuOptions['group:0']['to'] ?? [])->pluck('target')->all())->toBe(['group:1', 'group:2'])
        ->and(collect($cells)->where('week', 2)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(20)
        ->and(collect($cells)->where('week', 3)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(20)
        ->and(collect($cells)->where('week', 4)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(20)
        ->and(collect($cells)->where('week', 5)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(20);
});

it('resets only the selected visible scheduled session overrides', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 4, 'sessionsPerWeek' => 1, 'groupingMode' => 'groups', 'groupSize' => 2],
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(CalendarExerciseSettingsForm::class);

    $component->call('openForExercise', [
        'exerciseId' => $exercise->id,
        'exerciseProgramId' => $program->id,
        'weeks' => 4,
        'sessionsPerWeek' => 1,
        'weekLabels' => [],
        'weekSessions' => [1, 1, 1, 1],
        'scheduled' => true,
        'config' => $exercise->config->toArray(),
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 17, 0, true)
        ->call('updateCellOverride', 2, 0, 'reps', 21, 0, true)
        ->call('resetDisplayBucket', 'group:0');

    $cells = $component->get('data')['config']['overrides']['cells'] ?? [];

    expect(collect($cells)->where('week', 0)->first())->toBeNull()
        ->and(collect($cells)->where('week', 1)->first())->toBeNull()
        ->and(collect($cells)->where('week', 2)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(21)
        ->and(collect($cells)->where('week', 3)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(21);
});
