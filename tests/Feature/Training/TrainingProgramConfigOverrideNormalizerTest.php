<?php

use App\Data\Training\Config\ExerciseOverrides;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingProgramConfigOverrideNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('normalizes orphaned athlete overrides onto the live program pivot when there is one safe match', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create(['owner_id' => $coach->id]);
    $group = UserGroup::create(['owner_id' => $coach->id, 'name' => 'Armando']);

    $exercise = Exercise::factory()->create(['owner_id' => $coach->id]);
    $program = ExerciseProgram::factory()->create(['owner_id' => $coach->id]);
    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $program->config->setDefaultExerciseOverrides($pivot->id, ExerciseOverrides::from([
        'reps' => [
            'mode' => 'automatic',
            'default' => '14',
            'stepDownInterval' => 2,
            'decrement' => 2,
            'minimum' => 1,
            'applyPer' => 'per_set',
        ],
    ]));
    $program->config->setUserExerciseOverrides($athlete->id, 999999, ExerciseOverrides::from([
        'reps' => [
            'mode' => 'automatic',
            'default' => '14',
            'stepDownInterval' => 2,
            'decrement' => 2,
            'minimum' => 1,
            'applyPer' => 'per_set',
        ],
        'weight' => [
            'mode' => 'automatic',
            'oneRepMaxModifier' => 85,
            'default' => 5,
            'applyPer' => 'per_set',
        ],
    ]));
    $program->save();

    $trainingProgram = TrainingProgram::create([
        'owner_id' => $coach->id,
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $program->update([
        'parent_type' => TrainingProgram::class,
        'parent_id' => $trainingProgram->id,
    ]);

    $changed = app(TrainingProgramConfigOverrideNormalizer::class)->normalize($trainingProgram->fresh());

    expect($changed)->toBeTrue();

    $userOverrides = $trainingProgram->fresh()->program->config->toArray()['userExercises'];

    expect($userOverrides[$athlete->id][(string) $pivot->id]['weight']['oneRepMaxModifier'] ?? null)->toBe(85)
        ->and($userOverrides[$athlete->id]['999999'] ?? null)->toBeNull();
});
