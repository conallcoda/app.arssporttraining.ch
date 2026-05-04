<?php

use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Livewire\Training\View\ProgramEditor;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('persists program session grouping overrides from the shared settings section', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => SessionGroupingMode::Groups->value,
        'groupSize' => 2,
    ]);
    $coach->save();

    $program = ExerciseProgram::factory()->create();

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'planType' => ExerciseProgram::class,
        'showWeeksInput' => true,
    ])
        ->set('data.session_grouping.mode', SessionGroupingMode::None->value)
        ->assertSet('data.session_grouping.groupSize', 1);

    expect($program->fresh()->config->resolvedSessionGrouping()?->toArray())
        ->toBe([
            'mode' => SessionGroupingMode::None->value,
            'groupSize' => 1,
        ]);
});
