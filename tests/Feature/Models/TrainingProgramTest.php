<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Users\User;
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

it('materializes effective grouping defaults into the scheduled program copy on import', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'week',
        'groupSize' => 1,
        'copyValuesAutomatically' => true,
    ]);
    $coach->save();

    $group = UserGroup::create(['name' => 'Team Alpha']);
    $program = ExerciseProgram::factory()->create([
        'name' => 'Strength A',
        'owner_id' => $coach->id,
    ]);

    $exercise = Exercise::create(['name' => 'Bench Press']);
    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    $config = $program->config;
    $overrides = $config->defaultExerciseOverrides($pivot->id);
    $overrides->sessionGrouping = \App\Data\Exercise\Preview\SessionGroupingConfig::from([
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => false,
    ]);
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->save();

    $tp = TrainingProgram::importProgram($program, $group->id);
    $clonedProgram = $tp->program->fresh();

    expect($clonedProgram->config->resolvedSessionGrouping()?->toArray())
        ->toBe([
            'mode' => 'week',
            'groupSize' => 1,
            'copyValuesAutomatically' => true,
        ]);

    $clonedPivot = $clonedProgram->exercises()->first()?->pivot?->id;

    expect($clonedPivot)->not->toBeNull()
        ->and($clonedProgram->config->defaultExerciseOverrides((int) $clonedPivot)->sessionGrouping?->toArray())
            ->toBe([
                'mode' => 'groups',
                'groupSize' => 2,
                'copyValuesAutomatically' => false,
            ]);
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

it('stores override rows in the dedicated overrides table and not in the config json', function () {
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

    expect($rawConfig['overrideValues'] ?? null)->toBeNull()
        ->and($rawConfig['exercises'][$pivot->id]['gridOverrides'] ?? null)->toBeNull();

    $row = $program->planConfigOverrides()->first();

    expect($row)->not->toBeNull()
        ->and($row->program_exercise_id)->toBe($pivot->id)
        ->and($row->setting_key)->toBe('reps')
        ->and($row->target)->toBe('cell')
        ->and($row->scope)->toBe('current')
        ->and($row->getDecodedValue())->toBe(14);

    expect($program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides['cells'][0]['data']['reps'] ?? null)
        ->toBe(14);
});
