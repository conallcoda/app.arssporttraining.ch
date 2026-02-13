<?php

use App\Livewire\Test\Data\Preview\StrategyOrchestrator;
use App\Livewire\Test\Data\Settings\WeightProgressionSetting;

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
