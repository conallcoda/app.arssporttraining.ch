<?php

use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('hides automatic 1rm exercises for an athlete with no 1rm metric', function () {
    $athlete = User::factory()->athlete()->create();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps', 'weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'session'],
            'weight' => ['mode' => 'automatic', 'oneRepMaxModifier' => 100, 'applyPer' => 'session'],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 2,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [
            ['2026-05-01'],
            ['2026-05-08'],
        ],
        'lockedSessionsByWeek' => [
            [true],
            [false],
        ],
    ]);

    $grid = $component->instance()->previewGrid;
    $weightRow = collect($grid->rows)->firstWhere('field', 'weight');

    expect($component->instance()->isUnavailableForMissingMetrics)->toBeTrue()
        ->and($component->instance()->missingAthleteMetricLabels)->toBe(['1RM'])
        ->and($weightRow)->not->toBeNull()
        ->and($weightRow->getCellValue(0, 0, 0))->toBe('-')
        ->and($weightRow->getCellValue(1, 0, 0))->toBe('-')
        ->and($weightRow->editableMap[0][0] ?? null)->toBeFalse()
        ->and($weightRow->editableMap[1][0] ?? null)->toBeFalse();

    $component
        ->assertSee('Required metric missing')
        ->assertSee('1RM')
        ->assertDontSee('Set 1')
        ->call('openSettingsForm')
        ->assertNotDispatched('open-plan-exercise-settings');
});

it('hides automatic heart rate exercises for an athlete with no heart rate metric', function () {
    $athlete = User::factory()->athlete()->create();

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['heartRate', 'heartRateZone'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'heartRate' => ['mode' => 'automatic_jogging', 'applyPer' => 'session'],
            'heartRateZone' => ['default' => '2', 'applyPer' => 'session'],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-05-01', '2026-05-03'],
        ],
        'lockedSessionsByWeek' => [
            [false, false],
        ],
        'planMaxHR' => null,
        'planIatPercent' => null,
    ]);

    $grid = $component->instance()->previewGrid;
    $heartRateRow = collect($grid->rows)->firstWhere('field', 'heartRate');
    $zoneRow = collect($grid->rows)->firstWhere('field', 'heartRateZone');

    expect($component->instance()->isUnavailableForMissingMetrics)->toBeTrue()
        ->and($component->instance()->missingAthleteMetricLabels)->toBe(['heart rate'])
        ->and($heartRateRow)->not->toBeNull()
        ->and($zoneRow)->not->toBeNull()
        ->and($heartRateRow->getCellValue(0, 0, 0))->toBe('-')
        ->and($heartRateRow->getCellValue(0, 0, 1))->toBe('-')
        ->and($zoneRow->getCellValue(0, 0, 0))->toBe('-')
        ->and($zoneRow->getCellValue(0, 0, 1))->toBe('-');

    $component
        ->assertSee('Required metric missing')
        ->assertSee('heart rate')
        ->assertDontSee('Set 1')
        ->call('openSettingsForm')
        ->assertNotDispatched('open-plan-exercise-settings');
});
