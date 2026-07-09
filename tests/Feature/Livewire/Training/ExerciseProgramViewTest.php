<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Users\User;

it('renders the admin exercise program page', function () {
    $admin = User::factory()->admin()->create();
    $program = ExerciseProgram::factory()->create([
        'name' => 'Test Program',
    ]);

    $this->actingAs($admin)
        ->get('/admin/programs/'.$program->id)
        ->assertOk()
        ->assertSee('Test Program');
});

it('removes a program section exercise without a livewire update request', function () {
    $admin = User::factory()->admin()->create();
    $program = ExerciseProgram::factory()->create();
    $firstExercise = Exercise::factory()->create();
    $secondExercise = Exercise::factory()->create();

    $firstPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $firstExercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $secondPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $secondExercise->id,
        'sort' => 1,
        'type' => 'main',
    ]);

    $this->actingAs($admin)
        ->from('/admin/programs/'.$program->id)
        ->delete(route('training.programs.sections.exercises.destroy', [
            'exerciseProgram' => $program,
            'section' => 'main',
            'programExercise' => $firstPivot,
        ]), [
            'redirect' => '/admin/programs/'.$program->id,
        ])
        ->assertRedirect('/admin/programs/'.$program->id.'?section=main');

    $this->assertDatabaseMissing('exercise_program_exercises', [
        'id' => $firstPivot->id,
    ]);

    $this->assertDatabaseHas('exercise_program_exercises', [
        'id' => $secondPivot->id,
        'sort' => 0,
    ]);
});
