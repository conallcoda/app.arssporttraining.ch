<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExercisePlan;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramCategory;
use App\Models\Exercise\ExerciseProgramExercise;
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
    expect($tp->user)->toBeNull();
    expect($tp->sourcePlan)->toBeNull();
});

it('creates a training program for a user within a group', function () {
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $userTp = TrainingProgram::create([
        'group_id' => $group->id,
        'user_id' => $user->id,
        'exercise_program_id' => $program->id,
    ]);

    expect($userTp->user->id)->toBe($user->id);
    expect($userTp->group->id)->toBe($group->id);
});

it('scopes for group returns only group-level entries', function () {
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    TrainingProgram::create([
        'group_id' => $group->id,
        'user_id' => $user->id,
        'exercise_program_id' => $program->id,
    ]);

    expect(TrainingProgram::forGroup($group->id)->count())->toBe(1);
});

it('scopes for user returns only user-level entries', function () {
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $user = User::factory()->athlete()->create();
    $program1 = ExerciseProgram::factory()->create();
    $program2 = ExerciseProgram::factory()->create();

    TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program1->id,
    ]);

    TrainingProgram::create([
        'group_id' => $group->id,
        'user_id' => $user->id,
        'exercise_program_id' => $program2->id,
    ]);

    expect(TrainingProgram::forUser($group->id, $user->id)->count())->toBe(1);
    expect(TrainingProgram::forUser($group->id, $user->id)->first()->exercise_program_id)->toBe($program2->id);
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
    expect($tp->source_plan_id)->toBeNull();
});

it('imports an exercise by wrapping it in a program with category', function () {
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $category = ExerciseProgramCategory::factory()->create(['name' => 'Strength']);
    $exercise = Exercise::create(['name' => 'Deadlift']);

    $tp = TrainingProgram::importExercise($exercise, $group->id, categoryId: $category->id);

    expect($tp->program->name)->toBe('Deadlift');
    expect($tp->program->program_category_id)->toBe($category->id);
    expect($tp->program->exercises)->toHaveCount(1);
    expect($tp->program->exercises->first()->id)->toBe($exercise->id);
});

it('imports from a plan and copies all programs with source_plan_id', function () {
    $group = UserGroup::create(['name' => 'Team Alpha']);

    $program1 = ExerciseProgram::factory()->create(['name' => 'Program A']);
    $program2 = ExerciseProgram::factory()->create(['name' => 'Program B']);

    $plan = ExercisePlan::create([
        'name' => 'Test Plan',
        'config' => [
            'schedule' => [
                'startDate' => '',
                'weeks' => [
                    [
                        'id' => 'w1',
                        'sort' => 0,
                        'slots' => [
                            ['day' => 0, 'slot' => 0, 'programs' => [$program1->id]],
                            ['day' => 1, 'slot' => 0, 'programs' => [$program2->id]],
                        ],
                    ],
                ],
            ],
            'target' => [],
            'exercises' => [],
        ],
    ]);

    TrainingProgram::importFromPlan($plan, $group->id);

    $entries = TrainingProgram::forGroup($group->id)->orderBy('sort')->get();

    expect($entries)->toHaveCount(2);
    expect($entries[0]->source_plan_id)->toBe($plan->id);
    expect($entries[1]->source_plan_id)->toBe($plan->id);
    expect($entries[0]->sourcePlan->name)->toBe('Test Plan');
    expect($entries[0]->exercise_program_id)->not->toBe($program1->id);
    expect($entries[1]->exercise_program_id)->not->toBe($program2->id);
});

it('auto-increments sort order when adding multiple programs', function () {
    $group = UserGroup::create(['name' => 'Team Alpha']);

    TrainingProgram::importProgram(ExerciseProgram::factory()->create(), $group->id);
    TrainingProgram::importProgram(ExerciseProgram::factory()->create(), $group->id);
    TrainingProgram::importExercise(Exercise::create(['name' => 'Squat']), $group->id);

    $entries = TrainingProgram::forGroup($group->id)->orderBy('sort')->get();

    expect($entries)->toHaveCount(3);
    expect($entries[0]->sort)->toBe(0);
    expect($entries[1]->sort)->toBe(1);
    expect($entries[2]->sort)->toBe(2);
});

it('soft deletes a training program', function () {
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $program = ExerciseProgram::factory()->create();

    $tp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $tp->delete();

    expect($tp->trashed())->toBeTrue();
    expect(TrainingProgram::find($tp->id))->toBeNull();
    expect(TrainingProgram::withTrashed()->find($tp->id))->not->toBeNull();
});
