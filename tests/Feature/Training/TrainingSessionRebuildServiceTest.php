<?php

use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotStatusEnum;
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

it('rebuilds open exercise program slots from a specific date including past unrecorded sessions', function () {
    Carbon::setTestNow('2030-04-13 12:00:00');

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

    $pastOpenSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-12 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Pending,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-13 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
        'completed_at' => Carbon::parse('2030-04-13 10:00:00'),
    ]);

    $futureOpenSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-14 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Pending,
    ]);

    $seenIds = [];
    $mock = Mockery::mock(TrainingSessionMaterializer::class);
    $mock->shouldReceive('materialize')
        ->twice()
        ->withArgs(function (TrainingProgramSlot $slot, bool $force) use (&$seenIds): bool {
            $seenIds[] = $slot->id;

            return $force === true;
        });
    app()->instance(TrainingSessionMaterializer::class, $mock);

    app(TrainingSessionRebuildService::class)
        ->rebuildOpenSlotsForExerciseProgram($program->id, '2030-04-12');

    expect($seenIds)->toBe([$pastOpenSlot->id, $futureOpenSlot->id]);

    Carbon::setTestNow();
});

it('rebuilds future slots from the full plan timeline when grouping affects deloading', function () {
    Carbon::setTestNow('2030-04-12 12:00:00');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => [],
            'sets' => ['default' => 4, 'label' => 'Set', 'deload' => 'odd', 'deloadBy' => 1],
            'preview' => ['groupingMode' => 'none', 'groupSize' => 1],
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
    $overrides->sessionGrouping = SessionGroupingConfig::from([
        'mode' => 'none',
        'groupSize' => 1,
        'copyValuesAutomatically' => false,
    ]);
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->save();

    foreach (['2030-04-01', '2030-04-08', '2030-04-15', '2030-04-22'] as $date) {
        TrainingProgramSlot::factory()->create([
            'training_program_id' => $trainingProgram->id,
            'user_id' => $athlete->id,
            'datetime' => Carbon::parse($date.' 09:00:00'),
        ]);
    }

    $futureSlot = TrainingProgramSlot::query()
        ->where('datetime', '2030-04-15 09:00:00')
        ->firstOrFail();

    app(TrainingSessionMaterializer::class)->materialize($futureSlot, force: true);

    expect($futureSlot->fresh('exercises.sets')->exercises->firstOrFail()->sets)->toHaveCount(3);

    $config = $program->fresh()->config;
    $overrides = $config->defaultExerciseOverrides($pivot->id);
    $overrides->sessionGrouping = SessionGroupingConfig::from([
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => true,
    ]);
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->save();

    app(TrainingSessionRebuildService::class)->rebuildFutureSlotsForExerciseProgram($program->id);

    expect($futureSlot->fresh('exercises.sets')->exercises->firstOrFail()->sets)->toHaveCount(4);
});
