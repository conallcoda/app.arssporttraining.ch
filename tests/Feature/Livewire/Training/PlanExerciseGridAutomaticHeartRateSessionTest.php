<?php

use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('displays ungrouped strategy values by chronological slot rather than calendar week session', function () {
    $data = [
        'settings' => ['duration', 'heartRate', 'heartRateZone'],
        'sets' => ['default' => 2, 'label' => 'Interval', 'deload' => 'none'],
        'duration' => ['unit' => 'seconds', 'default' => 20, 'applyPer' => 'set'],
        'heartRate' => ['mode' => 'automatic_biking', 'applyPer' => 'set'],
        'heartRateZone' => ['default' => '3', 'applyPer' => 'set'],
        'preview' => [
            'weeks' => 4,
            'sessionsPerWeek' => 1,
            'groupingMode' => 'none',
            'groupSize' => 1,
            'copyValuesAutomatically' => false,
        ],
    ];
    $overrides = GridOverrides::fromConfig([
        'cells' => collect(range(0, 3))->map(fn (int $slot): array => [
            'week' => $slot,
            'session' => 0,
            'set' => 1,
            'data' => ['duration' => '40', 'heartRateZone' => '1'],
        ])->all(),
    ]);

    $grid = ExercisePreviewBuilder::build(
        data: $data,
        weeks: 3,
        overrides: $overrides,
        sessionsPerWeek: 2,
        maxHR: 193,
        iatPercent: 90,
        weekSessionDates: [
            ['2026-08-18', '2026-08-20'],
            ['2026-08-25', '2026-08-27'],
            ['2026-09-01', '2026-09-03'],
        ],
        explicitWeekSessionCounts: [2, 2, 2],
    );

    $durationRow = collect($grid->rows)->firstWhere('field', 'duration');
    $heartRateRow = collect($grid->rows)->firstWhere('field', 'heartRate');
    $zoneRow = collect($grid->rows)->firstWhere('field', 'heartRateZone');

    foreach ([[0, 0], [0, 1], [1, 0], [1, 1], [2, 0], [2, 1]] as [$week, $session]) {
        expect($durationRow->getCellValue($week, 1, $session))->toBe('40')
            ->and($heartRateRow->getCellValue($week, 1, $session))->toBe('125-144')
            ->and($zoneRow->getCellValue($week, 1, $session))->toBe('1')
            ->and($zoneRow->resolveCellColor($week, 1, true, $session))->toBe('bg-green-200 dark:bg-green-700/50');
    }
});

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

it('copies heart rate zones without persisting their derived automatic ranges', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['heartRate', 'heartRateZone'],
            'sets' => ['default' => 1, 'label' => 'Interval', 'deload' => 'none'],
            'heartRate' => ['mode' => 'automatic_biking', 'applyPer' => 'set'],
            'heartRateZone' => ['default' => '3', 'applyPer' => 'set'],
            'preview' => [
                'weeks' => 2,
                'sessionsPerWeek' => 1,
                'groupingMode' => 'none',
                'groupSize' => 1,
                'copyValuesAutomatically' => false,
            ],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();
    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $config = $program->config;
    $overrides = $config->defaultExerciseOverrides($pivot->id);
    $overrides->sessionGrouping = SessionGroupingConfig::from([
        'mode' => 'none',
        'groupSize' => 1,
        'copyValuesAutomatically' => false,
    ]);
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->save();

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 2,
        'sessionsPerWeek' => 1,
        'planMaxHR' => 193,
        'planIatPercent' => 90,
    ]);

    $component
        ->call('updateCellOverride', 0, 0, 'heartRateZone', '1', 0, false)
        ->call('copyDisplayBucket', 'session:0:0', 'session:1:0');

    $cells = collect($program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides['cells'] ?? []);
    $target = $cells->first(fn (array $cell): bool => ($cell['week'] ?? null) === 1
        && ($cell['session'] ?? null) === 0
        && ($cell['set'] ?? null) === 0);

    expect($target['data']['heartRateZone'] ?? null)->toBe('1')
        ->and($target['data'])->not->toHaveKey('heartRate');
});

it('still copies a genuine manual override of an automatic heart rate field', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['heartRate', 'heartRateZone'],
            'sets' => ['default' => 1, 'label' => 'Interval', 'deload' => 'none'],
            'heartRate' => ['mode' => 'automatic_biking', 'applyPer' => 'set'],
            'heartRateZone' => ['default' => '3', 'applyPer' => 'set'],
            'preview' => [
                'weeks' => 2,
                'sessionsPerWeek' => 1,
                'groupingMode' => 'none',
                'groupSize' => 1,
                'copyValuesAutomatically' => false,
            ],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();
    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $config = $program->config;
    $overrides = $config->defaultExerciseOverrides($pivot->id);
    $overrides->sessionGrouping = SessionGroupingConfig::from([
        'mode' => 'none',
        'groupSize' => 1,
        'copyValuesAutomatically' => false,
    ]);
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->save();

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 2,
        'sessionsPerWeek' => 1,
        'planMaxHR' => 193,
        'planIatPercent' => 90,
    ]);

    $component
        ->call('updateCellOverride', 0, 0, 'heartRate', '150-158', 0, false)
        ->call('copyDisplayBucket', 'session:0:0', 'session:1:0');

    $target = collect($program->fresh()->config->defaultExerciseOverrides($pivot->id)->gridOverrides['cells'] ?? [])
        ->first(fn (array $cell): bool => ($cell['week'] ?? null) === 1
            && ($cell['session'] ?? null) === 0
            && ($cell['set'] ?? null) === 0);

    expect($target['data']['heartRate'] ?? null)->toBe('150-158');
});
