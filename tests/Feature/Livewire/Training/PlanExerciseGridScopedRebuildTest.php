<?php

use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionRebuildDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('rebuilds only the edited athlete for athlete-specific exercise grid changes', function () {
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

    $athlete = User::factory()->create();

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldReceive('dispatchFutureSlotsForExerciseProgramChange')
        ->once()
        ->with($program->id, $athlete->id, '2026-04-30');
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgram');
    $mock->shouldNotReceive('dispatchFutureSlotsForAthleteExerciseProgram');
    $mock->shouldNotReceive('dispatchFutureSlotsForTrainingProgramChange');
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [['2026-04-30']],
        'lockedSessionsByWeek' => [[false]],
    ])->call('updateCellOverride', 0, 0, 'reps', 14, 0, false);
});

it('rebuilds only the selected scheduled training program when editing from the calendar plan', function () {
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

    $athlete = User::factory()->create();
    $group = UserGroup::query()->create(['name' => 'Training Group']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldReceive('dispatchFutureSlotsForTrainingProgramChange')
        ->once()
        ->with($trainingProgram->id, $athlete->id, '2026-04-30');
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgramChange');
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgram');
    $mock->shouldNotReceive('dispatchFutureSlotsForAthleteExerciseProgram');
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [['2026-04-30']],
        'lockedSessionsByWeek' => [[false]],
    ])->call('updateCellOverride', 0, 0, 'reps', 14, 0, false);
});

it('does not rebuild future slots for locked historical-only exercise grid changes', function () {
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

    $athlete = User::factory()->create();

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgramChange');
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgram');
    $mock->shouldNotReceive('dispatchFutureSlotsForAthleteExerciseProgram');
    $mock->shouldNotReceive('dispatchFutureSlotsForTrainingProgramChange');
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [['2026-04-30']],
        'lockedSessionsByWeek' => [[true]],
    ])->call('updateCellOverride', 0, 0, 'reps', 14, 0, false);

    $savedOverrides = $program->fresh()->config->exerciseOverrides($pivot->id, $athlete->id);

    expect($savedOverrides->gridOverrides['cells'])->toBeEmpty()
        ->and($savedOverrides->historicalGridOverrides['cells'])->toHaveCount(1);
});
