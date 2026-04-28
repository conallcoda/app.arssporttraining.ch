<?php

use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
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

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldReceive('dispatchFutureSlotsForAthleteExerciseProgram')
        ->once()
        ->with(20, $program->id);
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgram');
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(PlanExerciseGrid::class, [
        'exercisePlanId' => $program->id,
        'planType' => ExerciseProgram::class,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => 20,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [['2026-04-30']],
        'lockedSessionsByWeek' => [[false]],
    ])->call('updateCellOverride', 0, 0, 'reps', 14, 0, false);
});
