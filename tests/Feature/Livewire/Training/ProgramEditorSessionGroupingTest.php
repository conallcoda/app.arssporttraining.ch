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
        'copyValuesAutomatically' => true,
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
            'copyValuesAutomatically' => false,
        ]);
});

it('persists copy-values-automatically and normalizes grouped size to at least two', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => SessionGroupingMode::Week->value,
        'groupSize' => 1,
        'copyValuesAutomatically' => true,
    ]);
    $coach->save();
    $program = ExerciseProgram::factory()->create();

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'planType' => ExerciseProgram::class,
        'showWeeksInput' => true,
    ])
        ->set('data.session_grouping.mode', SessionGroupingMode::Groups->value)
        ->set('data.session_grouping.groupSize', 1)
        ->set('data.session_grouping.copyValuesAutomatically', false);

    expect($program->fresh()->config->resolvedSessionGrouping()?->toArray())
        ->toBe([
            'mode' => SessionGroupingMode::Groups->value,
            'groupSize' => 2,
            'copyValuesAutomatically' => false,
        ]);
});

it('defaults week mode to a size of one when switching grouping modes', function () {
    $coach = User::factory()->coach()->create();
    $program = ExerciseProgram::factory()->create();

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'planType' => ExerciseProgram::class,
        'showWeeksInput' => true,
    ])
        ->set('data.session_grouping.mode', SessionGroupingMode::Week->value)
        ->assertSet('data.session_grouping.groupSize', 1);

    expect($program->fresh()->config->resolvedSessionGrouping()?->toArray())
        ->toBe([
            'mode' => SessionGroupingMode::Week->value,
            'groupSize' => 1,
            'copyValuesAutomatically' => true,
        ]);
});

it('does not persist a concrete program grouping override when it matches the coach default', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => SessionGroupingMode::Week->value,
        'groupSize' => 1,
        'copyValuesAutomatically' => true,
    ]);
    $coach->save();

    $program = ExerciseProgram::factory()->create();

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'planType' => ExerciseProgram::class,
        'showWeeksInput' => true,
    ])
        ->set('data.session_grouping.mode', SessionGroupingMode::Week->value)
        ->set('data.session_grouping.groupSize', 1)
        ->set('data.session_grouping.copyValuesAutomatically', true);

    expect($program->fresh()->config->resolvedSessionGrouping())->toBeNull();
});
