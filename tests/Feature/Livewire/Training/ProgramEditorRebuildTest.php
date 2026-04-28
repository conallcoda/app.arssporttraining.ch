<?php

use App\Livewire\Training\View\ProgramEditor;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Training\TrainingSessionRebuildDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('dispatches a single shared rebuild when reordering section exercises', function () {
    $firstExercise = Exercise::factory()->create();
    $secondExercise = Exercise::factory()->create();
    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $firstExercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $secondExercise->id,
        'sort' => 1,
        'type' => 'main',
    ]);

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldReceive('dispatchFutureSlotsForExerciseProgramChange')
        ->once()
        ->with($program->id);
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgram');
    $mock->shouldNotReceive('dispatchFutureSlotsForAthleteExerciseProgram');
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
    ])->call('moveRelationshipItem', 'section_exercises', 1, -1);
});

it('dispatches a single shared rebuild when importing a section', function () {
    $targetExercise = Exercise::factory()->create();
    $sourceExerciseOne = Exercise::factory()->create();
    $sourceExerciseTwo = Exercise::factory()->create();

    $targetProgram = ExerciseProgram::factory()->create();
    $sourceProgram = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $targetProgram->id,
        'exercise_id' => $targetExercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $sourceProgram->id,
        'exercise_id' => $sourceExerciseOne->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $sourceProgram->id,
        'exercise_id' => $sourceExerciseTwo->id,
        'sort' => 1,
        'type' => 'main',
    ]);

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldReceive('dispatchFutureSlotsForExerciseProgramChange')
        ->once()
        ->with($targetProgram->id);
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgram');
    $mock->shouldNotReceive('dispatchFutureSlotsForAthleteExerciseProgram');
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $targetProgram,
        'planId' => $targetProgram->id,
    ])
        ->set('importProgramId', $sourceProgram->id)
        ->call('confirmImportSectionExercises');
});
