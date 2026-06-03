<?php

use App\Data\Exercise\Preview\StrategyOrchestrator;
use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Strategies\AutomaticStrategyFactory;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Training\Derivation\AutomaticRepsResolver;

it('populates sets per week from sets config', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 4, 'label' => 'Set'],
        'settings' => [],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 3);
    $state = $orchestrator->execute();

    expect($state->getSetsPerWeek())->toBe([4, 4, 4]);
});

it('populates sets per week with deload on odd weeks', function () {
    $data = [
        'sets' => ['deload' => 'odd', 'deloadBy' => 1, 'default' => 4, 'label' => 'Set'],
        'settings' => [],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 5);
    $state = $orchestrator->execute();

    expect($state->getSetsPerWeek())->toBe([3, 4, 3, 4, 3]);
});

it('applies deload grouping across session buckets', function () {
    $data = [
        'preview' => [
            'groupingMode' => 'groups',
            'groupSize' => 2,
            'sessionsPerWeek' => 2,
        ],
        'sets' => ['deload' => 'odd', 'deloadBy' => 1, 'default' => 4, 'label' => 'Set'],
        'settings' => [],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 3);
    $state = $orchestrator->execute();

    expect($state->getSetsPerWeek())->toBe([3, 4, 3])
        ->and($state->getResolvedSessionValue('sets', 0, 0, 4))->toBe(3)
        ->and($state->getResolvedSessionValue('sets', 0, 1, 4))->toBe(3)
        ->and($state->getResolvedSessionValue('sets', 1, 0, 4))->toBe(4)
        ->and($state->getResolvedSessionValue('sets', 1, 1, 4))->toBe(4)
        ->and($state->getResolvedSessionValue('sets', 2, 0, 4))->toBe(3)
        ->and($state->getResolvedSessionValue('sets', 2, 1, 4))->toBe(3);
});

it('populates automatic reps grid', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 4, 'label' => 'Set'],
        'settings' => ['reps'],
        'reps' => [
            'mode' => 'automatic',
            'default' => 10,
            'stepDownInterval' => 2,
            'decrement' => 2,
            'minimum' => 1,
            'applyPer' => 'session',
        ],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 3);
    $state = $orchestrator->execute();

    expect($state->hasGrid('reps'))->toBeTrue();

    $reps = $state->getGrid('reps');
    expect($reps)->toHaveCount(3);
    expect($reps[0])->toHaveCount(4);
});

it('progresses automatic reps by session group instead of calendar week', function () {
    $data = [
        'preview' => [
            'groupingMode' => 'groups',
            'groupSize' => 2,
            'sessionsPerWeek' => 2,
        ],
        'sets' => ['deload' => 'none', 'default' => 2, 'label' => 'Set'],
        'settings' => ['reps'],
        'reps' => [
            'mode' => 'automatic',
            'default' => 10,
            'stepDownInterval' => 1,
            'decrement' => 2,
            'minimum' => 1,
            'applyPer' => 'session',
        ],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 3);
    $state = $orchestrator->execute();

    expect($state->getResolvedCellValue('reps', 0, 0, 0))->toBe(10)
        ->and($state->getResolvedCellValue('reps', 0, 0, 1))->toBe(10)
        ->and($state->getResolvedCellValue('reps', 1, 0, 0))->toBe(8)
        ->and($state->getResolvedCellValue('reps', 1, 0, 1))->toBe(8)
        ->and($state->getResolvedCellValue('reps', 2, 0, 0))->toBe(6)
        ->and($state->getResolvedCellValue('reps', 2, 0, 1))->toBe(6);
});

it('uses the shared automatic strategy factory seam for automatic reps', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 2, 'label' => 'Set'],
        'settings' => ['reps'],
        'reps' => [
            'mode' => 'automatic',
            'default' => 10,
            'stepDownInterval' => 2,
            'decrement' => 2,
            'minimum' => 1,
            'applyPer' => 'session',
        ],
    ];

    $factory = new AutomaticStrategyFactory(
        repsResolver: new class extends AutomaticRepsResolver
        {
            public function buildGrid(RepsSetting $setting, int $weeks, array $setsPerWeek, array $groupIndexByWeekSession = []): array
            {
                return [
                    [99, 98],
                    [97, 96],
                ];
            }
        },
    );

    $orchestrator = new StrategyOrchestrator($data, weeks: 2, automaticStrategies: $factory);
    $state = $orchestrator->execute();

    expect($state->getGrid('reps'))->toBe([
        [99, 98],
        [97, 96],
    ]);
});

it('populates manual reps grid with default value', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 3, 'label' => 'Set'],
        'settings' => ['reps'],
        'reps' => [
            'mode' => 'manual',
            'default' => 8,
            'applyPer' => 'session',
        ],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 2);
    $state = $orchestrator->execute();

    expect($state->hasGrid('reps'))->toBeTrue();
    expect($state->getCellValue('reps', 0, 0))->toBe(8);
    expect($state->getCellValue('reps', 1, 2))->toBe(8);
});

it('shows blank manual reps as unspecified instead of restoring the default', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 2, 'label' => 'Set'],
        'settings' => ['reps'],
        'reps' => [
            'mode' => 'manual',
            'default' => null,
            'applyPer' => 'session',
        ],
    ];

    $state = (new StrategyOrchestrator($data, weeks: 1))->execute();

    expect($state->hasGrid('reps'))->toBeTrue();
    expect($state->getCellValue('reps', 0, 0))->toBe('-');
    expect($state->getCellValue('reps', 0, 1))->toBe('-');
});

it('keeps manual rep ranges as planned grid values', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 2, 'label' => 'Set'],
        'settings' => ['reps'],
        'reps' => [
            'mode' => 'manual',
            'default' => '8-10',
            'applyPer' => 'session',
        ],
    ];

    $state = (new StrategyOrchestrator($data, weeks: 1))->execute();

    expect($state->hasGrid('reps'))->toBeTrue();
    expect($state->getCellValue('reps', 0, 0))->toBe('8-10');
    expect($state->getCellValue('reps', 0, 1))->toBe('8-10');
});

it('coerces ambiguous manual reps when automatic weight depends on reps', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 2, 'label' => 'Set'],
        'settings' => ['reps', 'weight'],
        'reps' => [
            'mode' => 'manual',
            'default' => '8-10',
            'applyPer' => 'session',
        ],
        'weight' => [
            'mode' => 'automatic',
            'oneRepMaxModifier' => 100,
        ],
    ];

    $state = (new StrategyOrchestrator($data, weeks: 1))->execute();

    expect($state->hasGrid('reps'))->toBeTrue();
    expect($state->getCellValue('reps', 0, 0))->toBe(8);
    expect($state->getCellValue('reps', 0, 1))->toBe(8);
});

it('skips reps when not in settings', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 4, 'label' => 'Set'],
        'settings' => ['weight'],
        'weight' => ['mode' => 'automatic', 'oneRepMaxModifier' => 100, 'applyPer' => 'session'],
    ];

    $measuredData = new WeightProgressionSetting(measuredReps: 10, measuredWeight: 52, targetGoal: 7);

    $orchestrator = new StrategyOrchestrator($data, $measuredData, weeks: 3);
    $state = $orchestrator->execute();

    expect($state->hasGrid('reps'))->toBeFalse();
    expect($state->hasGrid('weight'))->toBeFalse();
});

it('populates weight grid when reps and weight are both configured', function () {
    $data = [
        'sets' => ['deload' => 'odd', 'deloadBy' => 1, 'default' => 4, 'label' => 'Set'],
        'settings' => ['reps', 'weight'],
        'reps' => [
            'mode' => 'automatic',
            'default' => 10,
            'stepDownInterval' => 2,
            'decrement' => 2,
            'minimum' => 1,
            'applyPer' => 'session',
        ],
        'weight' => [
            'mode' => 'automatic',
            'oneRepMaxModifier' => 100,
            'applyPer' => 'session',
        ],
    ];

    $measuredData = new WeightProgressionSetting(measuredReps: 10, measuredWeight: 52, targetGoal: 7);

    $orchestrator = new StrategyOrchestrator($data, $measuredData, weeks: 5);
    $state = $orchestrator->execute();

    expect($state->hasGrid('reps'))->toBeTrue();
    expect($state->hasGrid('weight'))->toBeTrue();
    expect($state->hasGrid('oneRepMax'))->toBeTrue();
    expect($state->getMetadata('weight', 'summary'))->not->toBeNull();
});

it('progresses automatic weight by session group and finishes on the last session', function () {
    $data = [
        'preview' => [
            'groupingMode' => 'groups',
            'groupSize' => 2,
            'sessionsPerWeek' => 2,
        ],
        'sets' => ['deload' => 'none', 'default' => 2, 'label' => 'Set'],
        'settings' => ['reps', 'weight'],
        'reps' => [
            'mode' => 'automatic',
            'default' => 10,
            'stepDownInterval' => 1,
            'decrement' => 2,
            'minimum' => 1,
            'applyPer' => 'session',
        ],
        'weight' => [
            'mode' => 'automatic',
            'oneRepMaxModifier' => 100,
            'applyPer' => 'session',
        ],
    ];

    $measuredData = new WeightProgressionSetting(measuredReps: 10, measuredWeight: 52, targetGoal: 7);

    $orchestrator = new StrategyOrchestrator($data, $measuredData, weeks: 3);
    $state = $orchestrator->execute();

    expect($state->getResolvedCellValue('weight', 0, 0, 0))->toBeLessThan($state->getResolvedCellValue('weight', 1, 0, 0))
        ->and($state->getResolvedCellValue('weight', 1, 0, 0))->toBeLessThan($state->getResolvedCellValue('weight', 2, 0, 0))
        ->and($state->getResolvedCellValue('oneRepMax', 2, 1, 1))->not->toBe('-')
        ->and($state->getResolvedCellValue('oneRepMax', 2, 1, 0))->toBe('-');
});

it('skips weight phase when measured data is null', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 4, 'label' => 'Set'],
        'settings' => ['reps', 'weight'],
        'reps' => [
            'mode' => 'automatic',
            'default' => 10,
            'stepDownInterval' => 2,
            'decrement' => 2,
            'minimum' => 1,
            'applyPer' => 'session',
        ],
        'weight' => [
            'mode' => 'automatic',
            'oneRepMaxModifier' => 100,
            'applyPer' => 'session',
        ],
    ];

    $orchestrator = new StrategyOrchestrator($data, null, weeks: 3);
    $state = $orchestrator->execute();

    expect($state->hasGrid('reps'))->toBeTrue();
    expect($state->hasGrid('weight'))->toBeFalse();
});

it('skips weight phase when weight mode is manual', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 4, 'label' => 'Set'],
        'settings' => ['reps', 'weight'],
        'reps' => [
            'mode' => 'automatic',
            'default' => 10,
            'stepDownInterval' => 2,
            'decrement' => 2,
            'minimum' => 1,
            'applyPer' => 'session',
        ],
        'weight' => [
            'mode' => 'manual',
            'default' => 50,
            'applyPer' => 'session',
        ],
    ];

    $measuredData = new WeightProgressionSetting(measuredReps: 10, measuredWeight: 52, targetGoal: 7);

    $orchestrator = new StrategyOrchestrator($data, $measuredData, weeks: 3);
    $state = $orchestrator->execute();

    expect($state->hasGrid('weight'))->toBeFalse();
});

it('populates manual reps grid with bilateral default value', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 3, 'label' => 'Set'],
        'settings' => ['reps'],
        'reps' => [
            'mode' => 'manual',
            'default' => '15_15',
            'applyPer' => 'session',
        ],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 2);
    $state = $orchestrator->execute();

    expect($state->hasGrid('reps'))->toBeTrue();
    expect($state->getCellValue('reps', 0, 0))->toBe('15_15');
    expect($state->getCellValue('reps', 1, 2))->toBe('15_15');
});

it('populates automatic reps grid with bilateral default', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 4, 'label' => 'Set'],
        'settings' => ['reps'],
        'reps' => [
            'mode' => 'automatic',
            'default' => '15_15',
            'stepDownInterval' => 2,
            'decrement' => 2,
            'minimum' => 1,
            'applyPer' => 'session',
        ],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 3);
    $state = $orchestrator->execute();

    expect($state->hasGrid('reps'))->toBeTrue();
    $cell = $state->getCellValue('reps', 0, 0);
    expect($cell)->toContain('_');
});

it('skips reps when applyPer is week', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 4, 'label' => 'Set'],
        'settings' => ['reps'],
        'reps' => [
            'mode' => 'automatic',
            'default' => 10,
            'applyPer' => 'week',
        ],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 3);
    $state = $orchestrator->execute();

    expect($state->hasGrid('reps'))->toBeFalse();
});

it('populates heart rate grid when automatic biking mode', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 3, 'label' => 'Set'],
        'settings' => ['heartRateZone', 'heartRate'],
        'heartRateZone' => ['default' => '2', 'applyPer' => 'session'],
        'heartRate' => ['mode' => 'automatic_biking', 'applyPer' => 'session'],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 2);
    $state = $orchestrator->execute();

    expect($state->hasGrid('heartRate'))->toBeTrue();
    expect($state->hasGrid('heartRateZone'))->toBeTrue();

    $hrGrid = $state->getGrid('heartRate');
    expect($hrGrid)->toHaveCount(2);
    expect($hrGrid[0])->toHaveCount(3);
});

it('populates heart rate grid when automatic jogging mode', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 3, 'label' => 'Set'],
        'settings' => ['heartRateZone', 'heartRate'],
        'heartRateZone' => ['default' => '1', 'applyPer' => 'session'],
        'heartRate' => ['mode' => 'automatic_jogging', 'applyPer' => 'session'],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 2);
    $state = $orchestrator->execute();

    expect($state->hasGrid('heartRate'))->toBeTrue();
});

it('skips heart rate phase when mode is manual', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 3, 'label' => 'Set'],
        'settings' => ['heartRate'],
        'heartRate' => ['mode' => 'manual', 'default' => '140', 'applyPer' => 'session'],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 2);
    $state = $orchestrator->execute();

    expect($state->hasGrid('heartRate'))->toBeFalse();
});

it('skips heart rate phase when applyPer is week', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 3, 'label' => 'Set'],
        'settings' => ['heartRate'],
        'heartRate' => ['mode' => 'automatic_biking', 'applyPer' => 'week'],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 2);
    $state = $orchestrator->execute();

    expect($state->hasGrid('heartRate'))->toBeFalse();
});

it('populates heart rate grid using default zone when heartRateZone not in settings', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 2, 'label' => 'Set'],
        'settings' => ['heartRate'],
        'heartRate' => ['mode' => 'automatic_biking', 'applyPer' => 'session'],
        'heartRateZone' => ['default' => '3'],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 2);
    $state = $orchestrator->execute();

    expect($state->hasGrid('heartRate'))->toBeTrue();
    expect($state->hasGrid('heartRateZone'))->toBeTrue();
});

it('populates heartRateZone grid in zone phase', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 3, 'label' => 'Set'],
        'settings' => ['heartRateZone'],
        'heartRateZone' => ['default' => '1', 'applyPer' => 'session'],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 2);
    $state = $orchestrator->execute();

    expect($state->hasGrid('heartRateZone'))->toBeTrue();
    expect($state->getCellValue('heartRateZone', 0, 0))->toBe('1');
    expect($state->getCellValue('heartRateZone', 1, 2))->toBe('1');
});

it('skips heartRateZone grid when applyPer is week', function () {
    $data = [
        'sets' => ['deload' => 'none', 'default' => 3, 'label' => 'Set'],
        'settings' => ['heartRateZone'],
        'heartRateZone' => ['default' => '1', 'applyPer' => 'week'],
    ];

    $orchestrator = new StrategyOrchestrator($data, weeks: 2);
    $state = $orchestrator->execute();

    expect($state->hasGrid('heartRateZone'))->toBeFalse();
});
