<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionMaterializer;
use App\Training\TrainingSessionRebuildService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('preloads compilation relations before materializing rebuilt future slots', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-12 09:00:00'),
    ]);

    $mock = Mockery::mock(TrainingSessionMaterializer::class);
    $mock->shouldReceive('materialize')
        ->twice()
        ->withArgs(function (TrainingProgramSlot $slot, bool $force): bool {
            return $force === true
                && $slot->relationLoaded('trainingProgram')
                && $slot->trainingProgram->relationLoaded('program')
                && $slot->trainingProgram->program->relationLoaded('exercises');
        });
    app()->instance(TrainingSessionMaterializer::class, $mock);

    app(TrainingSessionRebuildService::class)
        ->rebuildFutureSlotsForExerciseProgram($program->id);
});

it('can rebuild future exercise program slots from a specific date', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
    ]);

    $includedSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-12 09:00:00'),
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-14 09:00:00'),
    ]);

    $mock = Mockery::mock(TrainingSessionMaterializer::class);
    $mock->shouldReceive('materialize')
        ->twice()
        ->withArgs(fn (TrainingProgramSlot $slot, bool $force): bool => $force === true
            && $slot->datetime->gte($includedSlot->datetime));
    app()->instance(TrainingSessionMaterializer::class, $mock);

    app(TrainingSessionRebuildService::class)
        ->rebuildFutureSlotsForExerciseProgram($program->id, '2030-04-12');
});
