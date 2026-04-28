<?php

use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('updates automatic heart rate per session when heart rate zone differs in a mixed week', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['heartRate', 'heartRateZone'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'heartRate' => ['mode' => 'automatic_jogging', 'applyPer' => 'session'],
            'heartRateZone' => ['default' => '3', 'applyPer' => 'session'],
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
        'exercisePlanId' => $program->id,
        'planType' => ExerciseProgram::class,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
        ],
        'lockedSessionsByWeek' => [
            [true, false],
        ],
        'planMaxHR' => 193,
        'planIatPercent' => 90,
    ]);

    $component->call('updateCellOverride', 0, 0, 'heartRateZone', '0', 1, false);

    $grid = $component->instance()->previewGrid;
    $heartRateRow = collect($grid->rows)->firstWhere('field', 'heartRate');
    $zoneRow = collect($grid->rows)->firstWhere('field', 'heartRateZone');

    expect($heartRateRow)->not->toBeNull()
        ->and($zoneRow)->not->toBeNull()
        ->and($heartRateRow->getCellValue(0, 0, 0))->toBe('183-192')
        ->and($heartRateRow->getCellValue(0, 0, 1))->toBe('106-134')
        ->and($zoneRow->getCellValue(0, 0, 0))->toBe('3')
        ->and($zoneRow->getCellValue(0, 0, 1))->toBe('0')
        ->and($heartRateRow->resolveCellColor(0, 0, false, 0))->toBe('bg-red-200 dark:bg-red-700/50')
        ->and($heartRateRow->resolveCellColor(0, 0, true, 1))->toBe('bg-zinc-200 dark:bg-zinc-600/50');
});

it('does not recolor a locked session when another session zone changes in the same week', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['heartRate', 'heartRateZone'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'heartRate' => ['mode' => 'automatic_jogging', 'applyPer' => 'session'],
            'heartRateZone' => ['default' => '0', 'applyPer' => 'session'],
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
        'exercisePlanId' => $program->id,
        'planType' => ExerciseProgram::class,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
        ],
        'lockedSessionsByWeek' => [
            [true, false],
        ],
        'planMaxHR' => 193,
        'planIatPercent' => 90,
    ]);

    $component->call('updateCellOverride', 0, 0, 'heartRateZone', '1', 1, false);

    $grid = $component->instance()->previewGrid;
    $heartRateRow = collect($grid->rows)->firstWhere('field', 'heartRate');

    expect($heartRateRow)->not->toBeNull()
        ->and($heartRateRow->getCellValue(0, 0, 0))->toBe('106-134')
        ->and($heartRateRow->getCellValue(0, 0, 1))->toBe('135-153')
        ->and($heartRateRow->resolveCellColor(0, 0, false, 0))->toBe('bg-zinc-200 dark:bg-zinc-600/50')
        ->and($heartRateRow->resolveCellColor(0, 0, true, 1))->toBe('bg-green-200 dark:bg-green-700/50');
});
