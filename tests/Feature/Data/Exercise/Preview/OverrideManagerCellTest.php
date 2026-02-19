<?php

use App\Data\Exercise\Preview\OverrideManager;

function buildCellTestConfig(int $repsDefault = 8): array
{
    return [
        'settings' => ['reps', 'sets'],
        'reps' => ['mode' => 'manual', 'default' => $repsDefault, 'applyPer' => 'session'],
        'sets' => ['mode' => 'manual', 'default' => 3, 'deloadWeek' => 'none', 'label' => 'Set'],
        'overrides' => ['cells' => [], 'weeks' => []],
        'preview' => ['weeks' => 5, 'sessionsPerWeek' => 1],
    ];
}

it('sets a cell override when value differs from default', function () {
    $overrides = OverrideManager::reset();
    $config = buildCellTestConfig(8);

    $overrides = OverrideManager::updateCellOverride($overrides, $config, 5, 1, 0, 0, 'reps', 99, 0);

    expect($overrides['cells'])->toHaveCount(1);
    expect($overrides['cells'][0]['week'])->toBe(0);
    expect($overrides['cells'][0]['session'])->toBe(0);
    expect($overrides['cells'][0]['set'])->toBe(0);
    expect($overrides['cells'][0]['data']['reps'])->toBe(99);
});

it('updates existing cell override data field', function () {
    $overrides = ['cells' => [
        ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 10]],
    ], 'weeks' => []];

    $config = buildCellTestConfig(8);
    $overrides = OverrideManager::updateCellOverride($overrides, $config, 5, 1, 0, 0, 'weight', 50.0, 0);

    expect($overrides['cells'][0]['data'])->toHaveKey('reps');
    expect($overrides['cells'][0]['data'])->toHaveKey('weight');
    expect($overrides['cells'][0]['data']['weight'])->toBe(50.0);
});

it('removes a cell override when value matches default', function () {
    $config = buildCellTestConfig(8);

    $overrides = ['cells' => [
        ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 12]],
    ], 'weeks' => []];

    $defaultValue = OverrideManager::getDefaultCellValue($config, 5, 'reps', 0, 0);
    $overrides = OverrideManager::updateCellOverride($overrides, $config, 5, 1, 0, 0, 'reps', $defaultValue, 0);

    expect($overrides['cells'])->toBeEmpty();
});

it('removes entire cell entry when last field is removed', function () {
    $config = buildCellTestConfig(12);

    $defaultValue = OverrideManager::getDefaultCellValue($config, 5, 'reps', 1, 2);
    $overrides = ['cells' => [
        ['week' => 1, 'session' => 0, 'set' => 2, 'data' => ['reps' => 99]],
    ], 'weeks' => []];

    $overrides = OverrideManager::updateCellOverride($overrides, $config, 5, 1, 1, 2, 'reps', $defaultValue, 0);

    expect($overrides['cells'])->toBeEmpty();
});

it('applies cell override to all sessions when applyToAll is true', function () {
    $overrides = OverrideManager::reset();
    $config = buildCellTestConfig(8);
    $config['preview']['sessionsPerWeek'] = 3;

    $overrides = OverrideManager::updateCellOverride($overrides, $config, 5, 3, 0, 0, 'reps', 15, 0, true);

    expect($overrides['cells'])->toHaveCount(3);
    expect($overrides['cells'][0]['session'])->toBe(0);
    expect($overrides['cells'][1]['session'])->toBe(1);
    expect($overrides['cells'][2]['session'])->toBe(2);
});

it('computes default cell value via strategy orchestrator', function () {
    $config = buildCellTestConfig(8);

    $defaultValue = OverrideManager::getDefaultCellValue($config, 5, 'reps', 0, 0);

    expect($defaultValue)->toBe(8);
});

it('sets a bilateral reps cell override', function () {
    $overrides = OverrideManager::reset();
    $config = buildCellTestConfig(8);

    $overrides = OverrideManager::updateCellOverride($overrides, $config, 5, 1, 0, 0, 'reps', '10_10', 0);

    expect($overrides['cells'])->toHaveCount(1);
    expect($overrides['cells'][0]['data']['reps'])->toBe('10_10');
});
