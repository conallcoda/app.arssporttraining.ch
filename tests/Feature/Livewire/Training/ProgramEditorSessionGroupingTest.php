<?php

use App\Livewire\Training\View\ProgramEditor;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('does not expose session grouping controls in the program editor', function () {
    $coach = User::factory()->coach()->create();
    $program = ExerciseProgram::factory()->create();

    $component = Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'planType' => ExerciseProgram::class,
        'showWeeksInput' => true,
    ]);

    expect($component->get('data'))->not->toHaveKey('session_grouping')
        ->and($component->html())->not->toContain('Session Grouping')
        ->and($program->fresh()->config->resolvedSessionGrouping())->toBeNull();
});
