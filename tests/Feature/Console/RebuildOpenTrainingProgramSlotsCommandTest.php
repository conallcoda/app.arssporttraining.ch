<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionEditGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rebuilds mutable slots for one program and preserves immutable slots', function () {
    $group = UserGroup::create(['name' => 'Rebuild command']);
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create(['name' => 'Target program']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);
    $mutable = TrainingProgramSlot::withoutEvents(fn () => TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-01 09:00:00',
        'compiled_version' => 'stale-mutable',
    ]));
    $immutable = TrainingProgramSlot::withoutEvents(fn () => TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-03 09:00:00',
        'compiled_version' => 'stale-immutable',
    ]));
    $immutable->forceFill([
        'status' => TrainingProgramSlotStatusEnum::Completed,
        'completed_at' => '2030-04-03 10:00:00',
        'completed_exercise_count' => 1,
    ])->saveQuietly();

    expect(app(TrainingSessionEditGuard::class)->countImmutableSlotsForTrainingProgram($trainingProgram->id))->toBe(1);

    $this->artisan('training:rebuild-open-slots', [
        'trainingProgramId' => $trainingProgram->id,
    ])
        ->expectsOutputToContain('Rebuilt 1 mutable slots')
        ->assertSuccessful();

    expect($mutable->fresh()->compiled_version)->not->toBe('stale-mutable')
        ->and($immutable->fresh()->compiled_version)->toBe('stale-immutable');
});

it('can discard one athletes current grid overrides before rebuilding', function () {
    $group = UserGroup::create(['name' => 'Reset athlete overrides']);
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create(['name' => 'Target program']);
    $exercise = Exercise::factory()->create(['name' => 'Hamstring curls']);
    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);
    $config = $program->config;
    $defaults = $config->defaultExerciseOverrides($pivot->id);
    $defaults->gridOverrides = [
        'sessions' => [],
        'cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => '8']]],
    ];
    $defaults->baselineGridOverrides = $defaults->gridOverrides;
    $config->setDefaultExerciseOverrides($pivot->id, $defaults);
    $athleteOverrides = $config->userExerciseOverrides($athlete->id, $pivot->id);
    $athleteOverrides->gridOverrides = [
        'sessions' => [],
        'cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => '10']]],
    ];
    $config->setUserExerciseOverrides($athlete->id, $pivot->id, $athleteOverrides);
    $program->config = $config;
    $program->save();

    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);
    TrainingProgramSlot::withoutEvents(fn () => TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-01 09:00:00',
    ]));

    expect($program->fresh()->planConfigOverrides()
        ->where('user_id', $athlete->id)
        ->where('scope', 'current')
        ->count())->toBe(1);

    $this->artisan('training:rebuild-open-slots', [
        'trainingProgramId' => $trainingProgram->id,
        '--discard-athlete-overrides' => [$athlete->id],
        '--program-exercise' => [$pivot->id],
    ])
        ->expectsOutputToContain('Discarded 1 current calendar override rows')
        ->expectsOutputToContain("limited to program exercise pivot(s): {$pivot->id}")
        ->assertSuccessful();

    $freshProgram = $program->fresh();
    $freshConfig = $freshProgram->config;

    expect($freshProgram->planConfigOverrides()
        ->where('user_id', $athlete->id)
        ->where('scope', 'current')
        ->count())->toBe(0)
        ->and($freshConfig->defaultExerciseOverrides($pivot->id)->gridOverrides['cells'][0]['data']['reps'] ?? null)->toBe('8')
        ->and($freshConfig->userExerciseOverrides($athlete->id, $pivot->id)->gridOverrides['cells'] ?? [])->toBe([]);
});
