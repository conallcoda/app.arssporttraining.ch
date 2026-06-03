<?php

use App\Livewire\Training\View\ProgramEditor;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Users\User;
use App\Training\TrainingSessionRebuildDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('does not expose session grouping controls in the program editor', function () {
    $coach = User::factory()->coach()->create();
    $program = ExerciseProgram::factory()->create();

    $component = Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'showWeeksInput' => true,
    ]);

    expect($component->get('data'))->not->toHaveKey('session_grouping')
        ->and($component->html())->not->toContain('Session Grouping')
        ->and($program->fresh()->config->resolvedSessionGrouping())->toBeNull();
});

it('labels template duration as preview weeks', function () {
    $coach = User::factory()->coach()->create();
    $program = ExerciseProgram::factory()->create();

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'showWeeksInput' => true,
    ])
        ->assertSee('Preview Weeks')
        ->assertDontSee('Planned Weeks');
});

it('does not save blank or zero preview weeks', function () {
    $coach = User::factory()->coach()->create();
    $program = ExerciseProgram::factory()->create();
    $config = $program->config;
    $config->weeks = 5;
    $program->config = $config;
    $program->save();

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgramChange');
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'showWeeksInput' => true,
    ])
        ->set('weeks', '')
        ->assertHasErrors('weeks')
        ->assertSet('weeks', 5)
        ->set('weeks', 0)
        ->assertHasErrors('weeks')
        ->assertSet('weeks', 5);

    expect($program->fresh()->config->weeks)->toBe(5);
});
