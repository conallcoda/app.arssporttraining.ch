<?php

use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('saves future session overrides in a week that also has locked sessions', function () {
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
        'exercisePlanId' => $program->id,
        'planType' => ExerciseProgram::class,
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
    ])->call('updateCellOverride', 0, 0, 'reps', 14, 1, false);

    $overrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides;

    expect(collect($overrides['cells'] ?? [])->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 0 && ($cell['session'] ?? null) === 1 && ($cell['set'] ?? null) === 0))
        ->not->toBeNull()
        ->and(collect($overrides['cells'] ?? [])->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 0 && ($cell['session'] ?? null) === 1 && ($cell['set'] ?? null) === 0)['data']['reps'] ?? null)
        ->toBe(14);
});

it('preserves locked session values when editing a future session in the same week', function () {
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

    $config = $program->config;
    $config->setDefaultExerciseOverrides($pivot->id, \App\Data\Training\Config\ExerciseOverrides::from([
        'gridOverrides' => [
            'cells' => [
                ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => '11_11']],
            ],
            'sessions' => [],
        ],
    ]));
    $program->config = $config;
    $program->saveQuietly();

    Livewire::test(PlanExerciseGrid::class, [
        'exercisePlanId' => $program->id,
        'planType' => ExerciseProgram::class,
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
    ])->call('updateCellOverride', 0, 0, 'reps', '12_12', 1, false);

    $savedOverrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id);
    $overrides = $savedOverrides->gridOverrides;
    $historicalOverrides = $savedOverrides->historicalGridOverrides;

    expect(collect($overrides['cells'] ?? [])->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 0 && ($cell['session'] ?? null) === 0 && ($cell['set'] ?? null) === 0))
        ->toBeNull()
        ->and(collect($historicalOverrides['cells'] ?? [])->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 0 && ($cell['session'] ?? null) === 0 && ($cell['set'] ?? null) === 0)['data']['reps'] ?? null)
        ->toBe('11_11')
        ->and(collect($overrides['cells'] ?? [])->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 0 && ($cell['session'] ?? null) === 1 && ($cell['set'] ?? null) === 0)['data']['reps'] ?? null)
        ->toBe('12_12');
});

it('does not fan a future session edit out to other sessions even when applyToAll is requested', function () {
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

    $config = $program->config;
    $config->setDefaultExerciseOverrides($pivot->id, \App\Data\Training\Config\ExerciseOverrides::from([
        'gridOverrides' => [
            'cells' => [
                ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => '11_11']],
            ],
            'sessions' => [],
        ],
    ]));
    $program->config = $config;
    $program->saveQuietly();

    Livewire::test(PlanExerciseGrid::class, [
        'exercisePlanId' => $program->id,
        'planType' => ExerciseProgram::class,
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
    ])->call('updateCellOverride', 0, 0, 'reps', '12_12', 1, true);

    $savedOverrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id);
    $overrides = $savedOverrides->gridOverrides;
    $historicalOverrides = $savedOverrides->historicalGridOverrides;

    expect(collect($overrides['cells'] ?? [])->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 0 && ($cell['session'] ?? null) === 0 && ($cell['set'] ?? null) === 0))
        ->toBeNull()
        ->and(collect($historicalOverrides['cells'] ?? [])->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 0 && ($cell['session'] ?? null) === 0 && ($cell['set'] ?? null) === 0)['data']['reps'] ?? null)
        ->toBe('11_11')
        ->and(collect($overrides['cells'] ?? [])->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 0 && ($cell['session'] ?? null) === 1 && ($cell['set'] ?? null) === 0)['data']['reps'] ?? null)
        ->toBe('12_12');
});

it('fans apply-to-all edits out across unlocked sessions in the same week-group', function () {
    $coach = User::factory()->create();
    $this->actingAs($coach);

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
        'exercisePlanId' => $program->id,
        'planType' => ExerciseProgram::class,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
        ],
        'lockedSessionsByWeek' => [
            [false, false],
        ],
    ])->call('updateCellOverride', 0, 0, 'reps', 14, 1, true);

    $cells = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides['cells'] ?? [];

    expect(collect($cells)->where('week', 0)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(14)
        ->and(collect($cells)->where('week', 0)->where('session', 1)->first()['data']['reps'] ?? null)->toBe(14);
});

it('fans apply-to-all edits out across grouped sessions that span weeks', function () {
    $coach = User::factory()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
    ]);
    $coach->saveQuietly();
    Livewire::actingAs($coach);

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

    Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'exercisePlanId' => $program->id,
        'planType' => ExerciseProgram::class,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 2,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [
            ['2026-04-27'],
            ['2026-05-04'],
        ],
        'lockedSessionsByWeek' => [
            [false],
            [false],
        ],
    ])->call('updateCellOverride', 0, 0, 'reps', 16, 0, true);

    $cells = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides['cells'] ?? [];

    expect(collect($cells)->where('week', 0)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(16)
        ->and(collect($cells)->where('week', 1)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(16);
});

it('captures locked session values in the historical session snapshot bag for mixed weeks', function () {
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

    Livewire::test(PlanExerciseGrid::class, [
        'exercisePlanId' => $program->id,
        'planType' => ExerciseProgram::class,
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
    ])->call('updateSessionOverride', 0, 1, 'rest', 90);

    $savedOverrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id);

    expect(collect($savedOverrides->historicalGridOverrides['sessions'] ?? [])
        ->firstWhere(fn (array $session) => ($session['week'] ?? null) === 0 && ($session['session'] ?? null) === 0)['data']['rest'] ?? null)
        ->toBe(60)
        ->and(collect($savedOverrides->gridOverrides['sessions'] ?? [])
            ->firstWhere(fn (array $session) => ($session['week'] ?? null) === 0 && ($session['session'] ?? null) === 1)['data']['rest'] ?? null)
        ->toBe(90);
});

it('applies to all only across sessions that exist in the edited week', function () {
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
        'exercisePlanId' => $program->id,
        'planType' => ExerciseProgram::class,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 2,
        'sessionsPerWeek' => 2,
        'weekSessions' => [2, 1],
        'weekSessionDates' => [
            ['2026-04-30', '2026-05-01'],
            ['2026-05-08'],
        ],
        'lockedSessionsByWeek' => [
            [false, false],
            [false],
        ],
    ])->call('updateCellOverride', 1, 0, 'reps', '13_13', 0, true);

    $cells = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides['cells'] ?? [];

    expect(collect($cells)->where('week', 1)->where('session', 0)->first()['data']['reps'] ?? null)
        ->toBe('13_13')
        ->and(collect($cells)->where('week', 1)->where('session', 1)->first())
        ->toBeNull();
});
