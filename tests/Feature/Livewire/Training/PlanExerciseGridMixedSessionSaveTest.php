<?php

use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingPlanValueRevision;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
    ])->call('updateCellOverride', 0, 0, 'reps', 14, 1, false);

    $overrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides;

    expect(collect($overrides['cells'] ?? [])->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 0 && ($cell['session'] ?? null) === 1 && ($cell['set'] ?? null) === 0))
        ->not->toBeNull()
        ->and(collect($overrides['cells'] ?? [])->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 0 && ($cell['session'] ?? null) === 1 && ($cell['set'] ?? null) === 0)['data']['reps'] ?? null)
        ->toBe(14);
});

it('opens the exercise settings modal with inherited exercise instructions', function () {
    $program = ExerciseProgram::factory()->create();
    $exercise = Exercise::factory()->create([
        'video_url' => 'https://example.com/base-video',
        'instructions' => 'Keep the torso tall.',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);
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
    ])
        ->call('openSettingsForm')
        ->assertDispatched('open-plan-exercise-settings', function ($event, $params) {
            return ($params['data']['videoUrl'] ?? null) === 'https://example.com/base-video'
                && ($params['data']['instructions'] ?? null) === 'Keep the torso tall.';
        });
});

it('shows exercise instructions and media actions above the program exercise table', function () {
    if (! Schema::hasTable('media')) {
        (include base_path('vendor/coda/cms/database/migrations/0001_01_01_000100_create_media_table.php'))->up();
    }

    $program = ExerciseProgram::factory()->create();
    $exercise = Exercise::factory()->create([
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'instructions' => "Keep the torso tall.\nDrive the knees out.",
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);
    $photoPath = tempnam(sys_get_temp_dir(), 'exercise-photo-');
    file_put_contents($photoPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
    $exercise->addMedia($photoPath)
        ->usingName('goblet-squat.jpg')
        ->usingFileName('goblet-squat.png')
        ->toMediaCollection('photos');
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
        'exerciseName' => $exercise->name,
        'exerciseConfigArray' => $exercise->config->toArray(),
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
    ])
        ->assertSee('View instructions')
        ->assertSee("Keep the torso tall.\nDrive the knees out.")
        ->assertSee('whitespace-pre-line', false)
        ->assertSee('Watch video')
        ->assertSee('View gallery');
});

it('keeps only the current exercise plan config in each grid payload', function () {
    $program = ExerciseProgram::factory()->create();
    $firstExercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);
    $secondExercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'session'],
        ],
    ]);
    $firstPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $firstExercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);
    $secondPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $secondExercise->id,
        'sort' => 1,
        'type' => 'main',
    ]);
    $planConfigArray = [
        'target' => ['measuredReps' => 1, 'measuredWeight' => 50, 'targetGoal' => 10],
        'exercises' => [
            $firstPivot->id => ['instructions' => 'Keep this one.'],
            $secondPivot->id => ['instructions' => 'Drop this one.'],
        ],
        'userExercises' => [
            10 => [
                $firstPivot->id => ['instructions' => 'Keep athlete override.'],
                $secondPivot->id => ['instructions' => 'Drop athlete override.'],
            ],
            11 => [
                $firstPivot->id => ['instructions' => 'Drop other athlete.'],
            ],
        ],
        'overrideValues' => [
            ['programExerciseId' => $firstPivot->id, 'userId' => null, 'settingKey' => 'reps', 'value' => 8],
            ['programExerciseId' => $secondPivot->id, 'userId' => null, 'settingKey' => 'reps', 'value' => 9],
            ['programExerciseId' => $firstPivot->id, 'userId' => 10, 'settingKey' => 'reps', 'value' => 10],
            ['programExerciseId' => $firstPivot->id, 'userId' => 11, 'settingKey' => 'reps', 'value' => 11],
        ],
    ];

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $firstPivot->id,
        'exerciseId' => $firstExercise->id,
        'userId' => 10,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'planConfigArray' => $planConfigArray,
    ]);

    expect($component->instance()->planConfigArray['exercises'])->toHaveKey($firstPivot->id)
        ->and($component->instance()->planConfigArray['exercises'])->not->toHaveKey($secondPivot->id)
        ->and($component->instance()->planConfigArray['userExercises'])->toHaveKey(10)
        ->and($component->instance()->planConfigArray['userExercises'])->not->toHaveKey(11)
        ->and($component->instance()->planConfigArray['userExercises'][10])->toHaveKey($firstPivot->id)
        ->and($component->instance()->planConfigArray['userExercises'][10])->not->toHaveKey($secondPivot->id)
        ->and($component->instance()->planConfigArray['overrideValues'])->toHaveCount(2);
});

it('saves program-specific exercise content from the settings modal', function () {
    $program = ExerciseProgram::factory()->create();
    $exercise = Exercise::factory()->create([
        'video_url' => 'https://example.com/base-video',
        'instructions' => 'Base cue.',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);
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
    ])
        ->call('onSettingsSaved', [
            'programExerciseId' => $pivot->id,
            'exerciseId' => $exercise->id,
            'userId' => null,
            'videoUrl' => 'https://example.com/program-video',
            'instructions' => 'Program-specific cue.',
            'config' => [
                'settings' => ['reps'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
                'preview' => ['weeks' => 1, 'sessionsPerWeek' => 1],
                'overrides' => ['sessions' => [], 'cells' => []],
            ],
        ])
        ->assertNotDispatched('exercise-overrides-changed')
        ->assertDispatched('exercise-content-overrides-changed')
        ->call('openSettingsForm')
        ->assertDispatched('open-plan-exercise-settings', function ($event, $params) {
            return ($params['data']['videoUrl'] ?? null) === 'https://example.com/program-video'
                && ($params['data']['instructions'] ?? null) === 'Program-specific cue.';
        });

    $overrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id);

    expect($overrides->videoUrl)->toBe('https://example.com/program-video')
        ->and($overrides->instructions)->toBe('Program-specific cue.');
});

it('records a plan revision batch when a coach creates a grid override', function () {
    $coach = User::factory()->coach()->create();
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
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'lockedSessionsByWeek' => [[false]],
    ])->call('updateCellOverride', 0, 0, 'reps', 14, 0, false);

    $batch = TrainingRevisionBatch::query()->latest('id')->first();
    $revision = TrainingPlanValueRevision::query()->latest('id')->first();

    expect($batch?->domain)->toBe('plan')
        ->and($batch?->action)->toBe('create_grid_overrides')
        ->and($batch?->changed_by)->toBe($coach->id)
        ->and($revision?->program_exercise_id)->toBe($pivot->id)
        ->and($revision?->setting_key)->toBe('reps')
        ->and($revision?->before_value_type)->toBeNull()
        ->and($revision?->after_value_type)->toBe('int')
        ->and($revision?->after_int_value)->toBe(14);
});

it('records a plan revision batch when an exercise grid override is reset', function () {
    $coach = User::factory()->coach()->create();
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
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'lockedSessionsByWeek' => [[false]],
    ])->call('updateCellOverride', 0, 0, 'reps', 14, 0, false)
        ->call('resetOverrides');

    $batch = TrainingRevisionBatch::query()->latest('id')->first();
    $revision = TrainingPlanValueRevision::query()->latest('id')->first();

    expect($batch?->domain)->toBe('plan')
        ->and($batch?->action)->toBe('reset_grid_overrides')
        ->and($revision?->before_value_type)->toBe('int')
        ->and($revision?->before_int_value)->toBe(14)
        ->and($revision?->after_value_type)->toBeNull();
});

it('records a plan revision batch when a coach changes settings from the modal', function () {
    $coach = User::factory()->coach()->create();

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

    $updatedConfig = $exercise->config->toArray();
    $updatedConfig['reps']['default'] = 14;

    Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'lockedSessionsByWeek' => [[false]],
    ])->call('onSettingsSaved', [
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'config' => $updatedConfig,
    ]);

    $batch = TrainingRevisionBatch::query()->latest('id')->first();
    $revision = TrainingPlanValueRevision::query()
        ->where('setting_key', 'reps.default')
        ->where('after_int_value', 14)
        ->latest('id')
        ->first();

    expect($batch?->domain)->toBe('plan')
        ->and($batch?->action)->toBe('create_setting_overrides')
        ->and($batch?->changed_by)->toBe($coach->id)
        ->and($revision?->program_exercise_id)->toBe($pivot->id)
        ->and($revision?->setting_key)->toBe('reps.default')
        ->and($revision?->before_value_type)->toBeNull()
        ->and($revision?->after_value_type)->toBe('int')
        ->and($revision?->after_int_value)->toBe(14);
});

it('records a plan revision batch when a coach resets modal settings back to the parent defaults', function () {
    $coach = User::factory()->coach()->create();

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

    $updatedConfig = $exercise->config->toArray();
    $updatedConfig['reps']['default'] = 14;

    Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'lockedSessionsByWeek' => [[false]],
    ])->call('onSettingsSaved', [
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'config' => $updatedConfig,
    ])->call('onSettingsSaved', [
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'config' => $exercise->config->toArray(),
    ]);

    $batch = TrainingRevisionBatch::query()->latest('id')->first();
    $revision = TrainingPlanValueRevision::query()
        ->where('setting_key', 'reps.default')
        ->where('before_int_value', 14)
        ->latest('id')
        ->first();

    expect($batch?->domain)->toBe('plan')
        ->and($batch?->action)->toBe('reset_setting_overrides')
        ->and($revision?->setting_key)->toBe('reps.default')
        ->and($revision?->before_value_type)->toBe('int')
        ->and($revision?->before_int_value)->toBe(14)
        ->and($revision?->after_value_type)->toBeNull();
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
        'planId' => $program->id,
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

it('exposes auto-copy as disabled on the grid when configured off for grouped sessions', function () {
    $program = ExerciseProgram::factory()->create([
        'config' => [
            'sessionGrouping' => [
                'mode' => 'groups',
                'groupSize' => 2,
                'copyValuesAutomatically' => false,
            ],
        ],
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);

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
        'weeks' => 2,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1, 1],
    ]);

    expect($component->instance()->displayGrid->autoCopyValuesAutomatically)->toBeFalse();
});

it('shows the coach grouping on the plan grid badge', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => true,
    ]);
    $coach->save();

    $program = ExerciseProgram::factory()->create();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
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
        'weeks' => 2,
        'sessionsPerWeek' => 1,
    ]);

    expect($component->instance()->groupingBadge)
        ->toBe([
            'label' => 'Grouped By Sessions (2)',
            'color' => null,
            'overridden' => false,
        ]);
});

it('opens exercise-specific grouping settings from the grouping badge', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => true,
    ]);
    $coach->save();

    $program = ExerciseProgram::factory()->create();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);

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
        'sessionsPerWeek' => 1,
    ])
        ->call('openGroupingForm')
        ->assertDispatched('open-plan-exercise-settings', function ($event, $params) use ($pivot, $exercise) {
            return ($params['data']['programExerciseId'] ?? null) === $pivot->id
                && ($params['data']['exerciseId'] ?? null) === $exercise->id
                && ($params['data']['focusField'] ?? null) === 'session_grouping'
                && ($params['data']['config']['preview']['groupingMode'] ?? null) === 'groups'
                && ($params['data']['config']['preview']['groupSize'] ?? null) === 2;
        });
});

it('uses a persisted exercise-level grouping override instead of coach defaults', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'none',
        'groupSize' => 1,
        'copyValuesAutomatically' => false,
    ]);
    $coach->save();

    $program = ExerciseProgram::factory()->create([
        'config' => [
            'exercises' => [],
        ],
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $config = $program->config;
    $overrides = $config->defaultExerciseOverrides($pivot->id);
    $overrides->sessionGrouping = \App\Data\Exercise\Preview\SessionGroupingConfig::from([
        'mode' => 'week',
        'groupSize' => 1,
        'copyValuesAutomatically' => false,
    ]);
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->save();

    $component = Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 2,
        'sessionsPerWeek' => 1,
        'weekLabels' => ['W1', 'W2'],
        'weekSessions' => [1, 1],
    ]);

    expect($component->instance()->groupingBadge)
        ->toBe([
            'label' => 'Grouped By Weeks (1)',
            'color' => 'green',
            'overridden' => true,
        ])
        ->and($component->instance()->displayGrid->showGroupColumn)->toBeTrue();
});

it('stores exercise-level grouping from the plan exercise settings modal', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => true,
    ]);
    $coach->save();

    $program = ExerciseProgram::factory()->create();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
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
        'weeks' => 2,
        'sessionsPerWeek' => 1,
        'weekLabels' => ['W1', 'W2'],
        'weekSessions' => [1, 1],
    ]);

    $component->call('onSettingsSaved', [
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'config' => array_replace_recursive($exercise->config->toArray(), [
            'preview' => [
                'groupingMode' => 'week',
                'groupSize' => 1,
                'copyValuesAutomatically' => true,
            ],
        ]),
    ]);

    $sessionGrouping = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->sessionGrouping;

    expect($sessionGrouping?->mode)->toBe('week')
        ->and($sessionGrouping?->groupSize)->toBe(1)
        ->and($component->instance()->groupingBadge)
            ->toBe([
                'label' => 'Grouped By Weeks (1)',
                'color' => 'green',
                'overridden' => true,
            ]);
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
    ])->call('updateSessionOverride', 0, 1, 'rest', 90);

    $savedOverrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id);

    expect(collect($savedOverrides->historicalGridOverrides['sessions'] ?? [])
        ->firstWhere(fn (array $session) => ($session['week'] ?? null) === 0 && ($session['session'] ?? null) === 0)['data']['rest'] ?? null)
        ->toBe(60)
        ->and(collect($savedOverrides->gridOverrides['sessions'] ?? [])
            ->firstWhere(fn (array $session) => ($session['week'] ?? null) === 0 && ($session['session'] ?? null) === 1)['data']['rest'] ?? null)
        ->toBe(90);
});

it('normalizes historical values across locked sessions in the same fixed group', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
    ]);
    $coach->save();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['rest'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'rest' => ['default' => 60, 'applyPer' => 'week'],
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

    $config = $program->config;
    $overrides = $config->defaultExerciseOverrides($pivot->id);
    $overrides->historicalGridOverrides = [
        'cells' => [],
        'sessions' => [
            ['week' => 0, 'session' => 0, 'data' => ['rest' => 30]],
            ['week' => 0, 'session' => 1, 'data' => ['rest' => 90]],
        ],
    ];
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->save();

    Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'lockedSessionsByWeek' => [
            [true, true],
        ],
        'valueDisplayMode' => 'actual',
    ])->call('updateSessionOverride', 0, 0, 'rest', 30);

    $savedOverrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id);
    $historicalSessions = collect($savedOverrides->historicalGridOverrides['sessions'] ?? []);

    expect($historicalSessions
        ->firstWhere(fn (array $session) => ($session['week'] ?? null) === 0 && ($session['session'] ?? null) === 0)['data']['rest'] ?? null)
        ->toBe(30)
        ->and($historicalSessions
            ->firstWhere(fn (array $session) => ($session['week'] ?? null) === 0 && ($session['session'] ?? null) === 1)['data']['rest'] ?? null)
        ->toBe(30);
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
        'planId' => $program->id,
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

it('copies visible grouped sessions to another visible grouped session', function () {
    $coach = User::factory()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
    ]);
    $coach->saveQuietly();

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
        'weeks' => 4,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [
            ['2026-04-27'],
            ['2026-05-04'],
            ['2026-05-11'],
            ['2026-05-18'],
        ],
        'lockedSessionsByWeek' => [
            [false],
            [false],
            [false],
            [false],
        ],
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 17, 0, true)
        ->call('copyDisplayBucket', 'group:0', 'group:1');

    $cells = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides['cells'] ?? [];

    expect(collect($cells)->where('week', 2)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(17)
        ->and(collect($cells)->where('week', 3)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(17);
});

it('copies session buckets when grouping is none', function () {
    $coach = User::factory()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'none',
        'groupSize' => 1,
    ]);
    $coach->saveQuietly();

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
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 15, 0, false)
        ->call('copyDisplayBucket', 'session:0:0', 'session:1:0');

    $cells = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides['cells'] ?? [];

    expect(collect($cells)->where('week', 1)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(15);
});

it('copies a session bucket to all unlocked future sessions when grouping is none', function () {
    $coach = User::factory()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'none',
        'groupSize' => 1,
    ]);
    $coach->saveQuietly();

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
        'weeks' => 4,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [
            ['2026-04-27'],
            ['2026-05-04'],
            ['2026-05-11'],
            ['2026-05-18'],
        ],
        'lockedSessionsByWeek' => [
            [false],
            [true],
            [false],
            [false],
        ],
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 19, 0, false)
        ->call('copyDisplayBucketToAll', 'session:0:0');

    $copyMenuOptions = $component->get('copyMenuOptions');
    $cells = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides['cells'] ?? [];

    expect($copyMenuOptions['session:0:0']['toAll']['label'] ?? null)->toBe('All')
        ->and(collect($copyMenuOptions['session:0:0']['to'] ?? [])->pluck('target')->all())->toBe(['session:2:0', 'session:3:0'])
        ->and(collect($cells)->where('week', 1)->where('session', 0)->first())->toBeNull()
        ->and(collect($cells)->where('week', 2)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(19)
        ->and(collect($cells)->where('week', 3)->where('session', 0)->first()['data']['reps'] ?? null)->toBe(19);
});

it('exposes grouped copy buckets in plan mode when groups are collapsed', function () {
    $coach = User::factory()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
    ]);
    $coach->saveQuietly();

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
        'weeks' => 4,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [
            ['2026-04-27'],
            ['2026-05-04'],
            ['2026-05-11'],
            ['2026-05-18'],
        ],
        'lockedSessionsByWeek' => [
            [false],
            [false],
            [false],
            [false],
        ],
    ]);

    expect($component->get('copyBuckets'))
        ->toHaveKeys(['group:0', 'group:1'])
        ->not->toHaveKey('session:0:0')
        ->not->toHaveKey('session:3:0');
});

it('hides copy actions for locked groups in collapsed plan mode', function () {
    $coach = User::factory()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
    ]);
    $coach->saveQuietly();

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
        'weeks' => 4,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [
            ['2026-04-27'],
            ['2026-05-04'],
            ['2026-05-11'],
            ['2026-05-18'],
        ],
        'lockedSessionsByWeek' => [
            [true],
            [true],
            [false],
            [false],
        ],
    ]);

    $copyMenuOptions = $component->get('copyMenuOptions');

    expect($copyMenuOptions['group:0'])->toBe(['from' => [], 'to' => []])
        ->and($copyMenuOptions['group:1']['from'])->toHaveCount(0)
        ->and($copyMenuOptions['group:1']['to'])->toHaveCount(0);
});

it('renders grouped copy menu actions on collapsed plan groups', function () {
    $coach = User::factory()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 2,
    ]);
    $coach->saveQuietly();

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
        'weeks' => 4,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [
            ['2026-04-27'],
            ['2026-05-04'],
            ['2026-05-11'],
            ['2026-05-18'],
        ],
        'lockedSessionsByWeek' => [
            [false],
            [false],
            [false],
            [false],
        ],
    ]);

    $html = $component->html();

    expect($html)->toContain("copyDisplayBucket('group:0', 'group:1')")
        ->and($html)->toContain("copyDisplayBucketToAll('group:0')")
        ->and($html)->toContain("resetDisplayBucket('group:0')");
});

it('copies the source set count and all session fields to the target session', function () {
    $coach = User::factory()->create();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps', 'rest'],
            'sets' => ['default' => 4, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
            'rest' => ['default' => 60, 'applyPer' => 'per_session'],
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
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [
            ['2026-04-27'],
            ['2026-05-04'],
        ],
        'lockedSessionsByWeek' => [
            [false],
            [false],
        ],
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 10, 0, false)
        ->call('updateCellOverride', 0, 1, 'reps', 11, 0, false)
        ->call('updateCellOverride', 0, 2, 'reps', 16, 0, false)
        ->call('updateCellOverride', 0, 3, 'reps', 17, 0, false)
        ->call('updateSessionOverride', 0, 0, 'rest', 90, false)
        ->call('updateSessionOverride', 1, 0, 'sets', 3, false)
        ->call('copyDisplayBucket', 'session:0:0', 'session:1:0');

    $overrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides;
    $cells = collect($overrides['cells'] ?? []);
    $sessions = collect($overrides['sessions'] ?? []);

    expect($sessions->firstWhere(fn (array $session) => ($session['week'] ?? null) === 1 && ($session['session'] ?? null) === 0)['data']['sets'] ?? null)
        ->toBeNull()
        ->and($sessions->firstWhere(fn (array $session) => ($session['week'] ?? null) === 1 && ($session['session'] ?? null) === 0)['data']['rest'] ?? null)->toBe(90)
        ->and($cells->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 1 && ($cell['session'] ?? null) === 0 && ($cell['set'] ?? null) === 0)['data']['reps'] ?? null)->toBe(10)
        ->and($cells->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 1 && ($cell['session'] ?? null) === 0 && ($cell['set'] ?? null) === 1)['data']['reps'] ?? null)->toBe(11)
        ->and($cells->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 1 && ($cell['session'] ?? null) === 0 && ($cell['set'] ?? null) === 2)['data']['reps'] ?? null)->toBe(16)
        ->and($cells->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 1 && ($cell['session'] ?? null) === 0 && ($cell['set'] ?? null) === 3)['data']['reps'] ?? null)->toBe(17);
});

it('clears copied target cells outside the source set count', function () {
    $coach = User::factory()->create();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 4, 'label' => 'Set', 'deload' => 'none'],
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
    ]);

    $component->call('updateSessionOverride', 0, 0, 'sets', 3, false)
        ->call('updateCellOverride', 0, 0, 'reps', 10, 0, false)
        ->call('updateCellOverride', 0, 1, 'reps', 11, 0, false)
        ->call('updateCellOverride', 0, 2, 'reps', 16, 0, false)
        ->call('updateCellOverride', 1, 3, 'reps', 20, 0, false)
        ->call('copyDisplayBucket', 'session:0:0', 'session:1:0');

    $overrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides;
    $cells = collect($overrides['cells'] ?? []);
    $sessions = collect($overrides['sessions'] ?? []);

    expect($sessions->firstWhere(fn (array $session) => ($session['week'] ?? null) === 1 && ($session['session'] ?? null) === 0)['data']['sets'] ?? null)
        ->toBe(3)
        ->and($cells->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 1 && ($cell['session'] ?? null) === 0 && ($cell['set'] ?? null) === 0)['data']['reps'] ?? null)->toBe(10)
        ->and($cells->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 1 && ($cell['session'] ?? null) === 0 && ($cell['set'] ?? null) === 1)['data']['reps'] ?? null)->toBe(11)
        ->and($cells->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 1 && ($cell['session'] ?? null) === 0 && ($cell['set'] ?? null) === 2)['data']['reps'] ?? null)->toBe(16)
        ->and($cells->firstWhere(fn (array $cell) => ($cell['week'] ?? null) === 1 && ($cell['session'] ?? null) === 0 && ($cell['set'] ?? null) === 3))->toBeNull();
});

it('resets only the selected visible collapsed week group overrides', function () {
    $coach = User::factory()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'week',
        'groupSize' => 2,
    ]);
    $coach->saveQuietly();

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
        'weeks' => 2,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
            ['2026-05-04', '2026-05-07'],
        ],
        'lockedSessionsByWeek' => [
            [false, false],
            [false, false],
        ],
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 18, 0, true)
        ->call('updateCellOverride', 1, 0, 'reps', 22, 0, true)
        ->call('resetDisplayBucket', 'group:0');

    $cells = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides['cells'] ?? [];

    expect(collect($cells)->where('week', 0)->first())->toBeNull()
        ->and(collect($cells)->where('week', 1)->first())->toBeNull();
});

it('exercise-level reset removes all overrides for the exercise', function () {
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
        'weeks' => 2,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
            ['2026-05-04', '2026-05-07'],
        ],
        'lockedSessionsByWeek' => [
            [false, false],
            [false, false],
        ],
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 18, 0, true)
        ->call('updateCellOverride', 1, 0, 'reps', 22, 0, true)
        ->call('resetOverrides');

    $overrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides;

    expect($overrides['cells'] ?? [])->toBe([])
        ->and($overrides['sessions'] ?? [])->toBe([]);
});
