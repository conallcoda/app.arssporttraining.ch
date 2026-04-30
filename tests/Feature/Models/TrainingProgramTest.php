<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Users\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a training program for a group', function () {
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $program = ExerciseProgram::factory()->create();

    $tp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    expect($tp->group->id)->toBe($group->id);
    expect($tp->program->id)->toBe($program->id);
});

it('imports a program by duplicating it', function () {
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $program = ExerciseProgram::factory()->create(['name' => 'Strength A']);

    $exercise = Exercise::create(['name' => 'Bench Press']);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    $tp = TrainingProgram::importProgram($program, $group->id);

    expect($tp->exercise_program_id)->not->toBe($program->id);
    expect($tp->program->name)->toBe('Strength A');
    expect($tp->program->exercises)->toHaveCount(1);
});

it('imports an exercise by wrapping it in a program with category', function () {
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $category = Tag::create(['scope' => 'exercise_category', 'name' => 'Strength', 'slug' => 'strength-test']);
    $exercise = Exercise::create(['name' => 'Deadlift']);

    $tp = TrainingProgram::importExercise($exercise, $group->id, categoryId: $category->id);

    expect($tp->program->name)->toBe('Deadlift');
    expect($tp->program->exercise_category_id)->toBe($category->id);
    expect($tp->program->exercises)->toHaveCount(1);
    expect($tp->program->exercises->first()->id)->toBe($exercise->id);
});


it('auto-increments sort order when adding multiple programs', function () {
    $group = UserGroup::create(['name' => 'Team Alpha']);

    TrainingProgram::importProgram(ExerciseProgram::factory()->create(), $group->id);
    TrainingProgram::importProgram(ExerciseProgram::factory()->create(), $group->id);
    TrainingProgram::importExercise(Exercise::create(['name' => 'Squat']), $group->id);

    $entries = TrainingProgram::where('group_id', $group->id)->orderBy('sort')->get();

    expect($entries)->toHaveCount(3);
    expect($entries[0]->sort)->toBe(0);
    expect($entries[1]->sort)->toBe(1);
    expect($entries[2]->sort)->toBe(2);
});

it('deletes a training program', function () {
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $program = ExerciseProgram::factory()->create();

    $tp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $tp->delete();

    expect(TrainingProgram::find($tp->id))->toBeNull();
});

it('stores flattened override values in the exercise program config payload', function () {
    $program = ExerciseProgram::factory()->create();
    $exercise = Exercise::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $config = $program->config;
    $overrides = $config->defaultExerciseOverrides($pivot->id);
    $overrides->gridOverrides = [
        'sessions' => [],
        'cells' => [
            ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 14]],
        ],
    ];
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->saveQuietly();

    $rawConfig = json_decode((string) $program->fresh()->getRawOriginal('config'), true);

    expect($rawConfig['overrideValues'][0]['programExerciseId'] ?? null)->toBe($pivot->id)
        ->and($rawConfig['overrideValues'][0]['settingKey'] ?? null)->toBe('reps')
        ->and($rawConfig['exercises'][$pivot->id]['gridOverrides'] ?? null)->toBeNull()
        ->and($program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides['cells'][0]['data']['reps'] ?? null)->toBe(14);
});
