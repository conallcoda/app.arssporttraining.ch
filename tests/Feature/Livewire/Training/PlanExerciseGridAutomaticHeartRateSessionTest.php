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
        'planId' => $program->id,
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
        ->and($heartRateRow->resolveCellColor(0, 0, false, 0))->toBe('bg-red-100 dark:bg-red-800/40')
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
        'planId' => $program->id,
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
        ->and($heartRateRow->resolveCellColor(0, 0, false, 0))->toBe('bg-zinc-100 dark:bg-zinc-700/40')
        ->and($heartRateRow->resolveCellColor(0, 0, true, 1))->toBe('bg-green-200 dark:bg-green-700/50');
});

it('lets admins manually override automatic heart rate values', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['heartRate', 'heartRateZone'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'heartRate' => ['mode' => 'automatic_biking', 'applyPer' => 'session'],
            'heartRateZone' => ['default' => '1', 'applyPer' => 'session'],
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
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [
            ['2026-04-30'],
        ],
        'lockedSessionsByWeek' => [
            [false],
        ],
        'planMaxHR' => 193,
        'planIatPercent' => 90,
    ]);

    $component->call('updateCellOverride', 0, 0, 'heartRate', '150-158', 0, false);

    $grid = $component->instance()->previewGrid;
    $heartRateRow = collect($grid->rows)->firstWhere('field', 'heartRate');

    expect($heartRateRow)->not->toBeNull()
        ->and($heartRateRow->getCellValue(0, 0, 0))->toBe('150-158')
        ->and($heartRateRow->isCellOverriddenAt(0, 0, 0))->toBeTrue();

    $overrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides;

    expect(collect($overrides['cells'] ?? [])->firstWhere(
        fn (array $cell) => ($cell['week'] ?? null) === 0
            && ($cell['session'] ?? null) === 0
            && ($cell['set'] ?? null) === 0
    )['data']['heartRate'] ?? null)->toBe('150-158');
});
