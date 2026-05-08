<?php

use App\Livewire\Training\View\ProgramEditor;
use App\Livewire\Athlete\DaySchedule;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows the athlete preview button in the settings panel when an athlete is selected', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'userId' => $athlete->id,
        'showActualValueTabs' => true,
    ])->assertSee('Preview');
});

it('does not show the athlete preview button without a selected athlete', function () {
    $coach = User::factory()->coach()->create();
    $program = ExerciseProgram::factory()->create();

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'showActualValueTabs' => true,
    ])->assertDontSee('Preview');
});

it('bumps the parent grid render version when exercise overrides change', function () {
    $coach = User::factory()->coach()->create();
    $program = ExerciseProgram::factory()->create();

    $component = Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'showActualValueTabs' => true,
    ]);

    expect($component->instance()->gridRenderVersion)->toBe(0);

    $component->call('onExerciseOverridesChanged');

    expect($component->instance()->gridRenderVersion)->toBe(1);
});

it('renders program preview sessions and opens athlete-style session details', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $category = Tag::factory()->withScope('exercise_category')->create([
        'name' => 'Strength',
        'short_name' => 'STR',
        'color' => '#22c55e',
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps', 'rest'],
            'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
            'rest' => ['default' => 90, 'applyPer' => 'session'],
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create([
        'exercise_category_id' => $category->id,
    ]);

    $group = UserGroup::query()->create([
        'name' => 'Preview Group',
        'owner_id' => $coach->id,
    ]);
    $group->members()->attach($athlete->id);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-05-06 08:00:00',
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-05-08 08:00:00',
    ]);

    Livewire::actingAs($coach)->test(DaySchedule::class, [
        'date' => '2026-05-06',
        'showReadiness' => false,
        'previewMode' => true,
        'previewTrainingProgramId' => $trainingProgram->id,
        'previewUserId' => $athlete->id,
    ])
        ->assertSee('08:00')
        ->call('openPreviewSession', '2026-05-06')
        ->assertSee('Back to Preview')
        ->assertSee('Front Squat')
        ->assertSee('Reps')
        ->assertSee('8');
});
