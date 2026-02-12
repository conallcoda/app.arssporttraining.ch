<?php

use App\Livewire\Test\Data\Preview\GridState;
use App\Livewire\Test\Data\Settings\WeightSetting;
use App\Livewire\Test\Data\Strategies\Weight\MeasuredData;
use App\Livewire\Test\Data\Strategies\Weight\OneRepMaxFixedStrategy;
use App\Training\Reference\RepPercentageTable;

function buildStateWithReps(array $setsPerWeek, array $reps): GridState
{
    $state = new GridState;
    $state->setSetsPerWeek($setsPerWeek);
    $state->setGrid('reps', $reps);

    return $state;
}

it('returns null when measured data is incomplete', function () {
    $setting = WeightSetting::from(['mode' => 'automatic', 'oneRepMaxModifier' => 100]);
    $measuredData = new MeasuredData;
    $state = new GridState;
    $state->setSetsPerWeek([4, 4]);

    $strategy = new OneRepMaxFixedStrategy($setting, $measuredData);

    expect($strategy->generate(2, $state))->toBeNull();
});

it('adjusts anchor weight based on last set reps', function () {
    $setting = WeightSetting::from(['mode' => 'automatic', 'oneRepMaxModifier' => 100]);
    $measuredData = new MeasuredData(measuredReps: 10, measuredWeight: 52, targetGoal: 7);

    $setsPerWeek = [3, 4, 3, 4, 3];
    $reps = [
        [10, 10, 8],
        [10, 10, 8, 8],
        [8, 8, 6],
        [8, 8, 6, 6],
        [6, 6, 4],
    ];
    $state = buildStateWithReps($setsPerWeek, $reps);

    $strategy = new OneRepMaxFixedStrategy($setting, $measuredData);
    $result = $strategy->generate(5, $state);

    expect($result)->not->toBeNull();

    $lastWeekIndex = 4;
    $lastSetIndex = 2;
    $lastSetWeight = $result['weights'][$lastWeekIndex][$lastSetIndex];
    $lastSetReps = $reps[$lastWeekIndex][$lastSetIndex];

    $repPercentage = RepPercentageTable::getPercentage($lastSetReps);
    expect($repPercentage)->toBe(0.91);

    $repPercentage1Rep = RepPercentageTable::getPercentage(1);
    expect($repPercentage1Rep)->toBe(1.0);
    expect($lastSetWeight)->toBeLessThan($result['oneRepMax'][$lastWeekIndex][$lastSetIndex]);
});

it('uses rep percentage of 1.0 when reps grid has no data for last set', function () {
    $setting = WeightSetting::from(['mode' => 'automatic', 'oneRepMaxModifier' => 100]);
    $measuredData = new MeasuredData(measuredReps: 1, measuredWeight: 100, targetGoal: 0);

    $state = new GridState;
    $state->setSetsPerWeek([2, 2]);

    $strategy = new OneRepMaxFixedStrategy($setting, $measuredData);
    $result = $strategy->generate(2, $state);

    expect($result)->not->toBeNull();
    expect($result['weights'][1][1])->toBe(100.0);
});

it('writes weight grid and metadata to state', function () {
    $setting = WeightSetting::from(['mode' => 'automatic', 'oneRepMaxModifier' => 100]);
    $measuredData = new MeasuredData(measuredReps: 10, measuredWeight: 52, targetGoal: 7);

    $setsPerWeek = [3, 4, 3, 4, 3];
    $reps = [
        [10, 10, 8],
        [10, 10, 8, 8],
        [8, 8, 6],
        [8, 8, 6, 6],
        [6, 6, 4],
    ];
    $state = buildStateWithReps($setsPerWeek, $reps);

    $strategy = new OneRepMaxFixedStrategy($setting, $measuredData);
    $strategy->generate(5, $state);

    expect($state->hasGrid('weight'))->toBeTrue();
    expect($state->hasGrid('oneRepMax'))->toBeTrue();

    $summary = $state->getMetadata('weight', 'summary');
    expect($summary)->not->toBeNull();
    expect($summary)->toHaveKeys(['starting1RM', 'target1RM', 'targetGoal']);
    expect($summary['targetGoal'])->toBe(7);
});

it('places 1RM value only in the last set of the last week', function () {
    $setting = WeightSetting::from(['mode' => 'automatic', 'oneRepMaxModifier' => 100]);
    $measuredData = new MeasuredData(measuredReps: 10, measuredWeight: 52, targetGoal: 7);

    $setsPerWeek = [3, 3, 3];
    $reps = [
        [10, 10, 8],
        [8, 8, 6],
        [6, 6, 4],
    ];
    $state = buildStateWithReps($setsPerWeek, $reps);

    $strategy = new OneRepMaxFixedStrategy($setting, $measuredData);
    $result = $strategy->generate(3, $state);

    expect($result['oneRepMax'][0][0])->toBe('-');
    expect($result['oneRepMax'][0][1])->toBe('-');
    expect($result['oneRepMax'][0][2])->toBe('-');
    expect($result['oneRepMax'][1][0])->toBe('-');
    expect($result['oneRepMax'][1][1])->toBe('-');
    expect($result['oneRepMax'][1][2])->toBe('-');
    expect($result['oneRepMax'][2][0])->toBe('-');
    expect($result['oneRepMax'][2][1])->toBe('-');
    expect($result['oneRepMax'][2][2])->toBeFloat();
});

it('produces weights that increase week over week for the last set', function () {
    $setting = WeightSetting::from(['mode' => 'automatic', 'oneRepMaxModifier' => 100]);
    $measuredData = new MeasuredData(measuredReps: 10, measuredWeight: 52, targetGoal: 7);

    $setsPerWeek = [4, 4, 4, 4, 4];
    $reps = [
        [10, 10, 8, 8],
        [10, 10, 8, 8],
        [8, 8, 6, 6],
        [8, 8, 6, 6],
        [6, 6, 4, 4],
    ];
    $state = buildStateWithReps($setsPerWeek, $reps);

    $strategy = new OneRepMaxFixedStrategy($setting, $measuredData);
    $result = $strategy->generate(5, $state);

    for ($week = 1; $week < 5; $week++) {
        $lastSet = count($result['weights'][$week]) - 1;
        $prevLastSet = count($result['weights'][$week - 1]) - 1;
        expect($result['weights'][$week][$lastSet])
            ->toBeGreaterThanOrEqual($result['weights'][$week - 1][$prevLastSet]);
    }
});
