<?php

use App\Livewire\Training\CalendarExerciseSettingsForm;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('applies to all only across sessions that exist in the scheduled week preview', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'preview' => ['weeks' => 2, 'sessionsPerWeek' => 2],
            'overrides' => ['cells' => [], 'weeks' => []],
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
            'overrides' => ['cells' => [], 'weeks' => []],
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
