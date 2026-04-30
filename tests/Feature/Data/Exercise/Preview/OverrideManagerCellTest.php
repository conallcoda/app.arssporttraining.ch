<?php

use App\Data\Exercise\Preview\OverrideManager;

function buildCellTestConfig(int $repsDefault = 8): array
{
    return [
        'settings' => ['reps', 'sets'],
        'reps' => ['mode' => 'manual', 'default' => $repsDefault, 'applyPer' => 'session'],
        'sets' => ['mode' => 'manual', 'default' => 3, 'deloadWeek' => 'none', 'label' => 'Set'],
        'overrides' => ['sessions' => [], 'cells' => []],
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
    ], 'sessions' => []];

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
    ], 'sessions' => []];

    $defaultValue = OverrideManager::getDefaultCellValue($config, 5, 'reps', 0, 0);
    $overrides = OverrideManager::updateCellOverride($overrides, $config, 5, 1, 0, 0, 'reps', $defaultValue, 0);

    expect($overrides['cells'])->toBeEmpty();
});

it('removes entire cell entry when last field is removed', function () {
    $config = buildCellTestConfig(12);

    $defaultValue = OverrideManager::getDefaultCellValue($config, 5, 'reps', 1, 2);
    $overrides = ['cells' => [
        ['week' => 1, 'session' => 0, 'set' => 2, 'data' => ['reps' => 99]],
    ], 'sessions' => []];

    $overrides = OverrideManager::updateCellOverride($overrides, $config, 5, 1, 1, 2, 'reps', $defaultValue, 0);

    expect($overrides['cells'])->toBeEmpty();
});

it('stores cell overrides only for the edited session even when applyToAll is true', function () {
    $overrides = OverrideManager::reset();
    $config = buildCellTestConfig(8);
    $config['preview']['sessionsPerWeek'] = 3;

    $overrides = OverrideManager::updateCellOverride($overrides, $config, 5, 3, 0, 0, 'reps', 15, 0, true);

    expect($overrides['cells'])->toHaveCount(1);
    expect($overrides['cells'][0]['session'])->toBe(0);
});

it('removes stale session overrides beyond the current week session count', function () {
    $config = buildCellTestConfig(8);
    $config['preview']['sessionsPerWeek'] = 3;

    $overrides = ['cells' => [
        ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 10]],
        ['week' => 0, 'session' => 1, 'set' => 0, 'data' => ['reps' => 11]],
        ['week' => 0, 'session' => 2, 'set' => 0, 'data' => ['reps' => 12]],
    ], 'sessions' => []];

    $overrides = OverrideManager::updateCellOverride($overrides, $config, 5, 3, 0, 0, 'reps', 15, 0, true, null, 1);

    expect($overrides['cells'])->toHaveCount(3);
    expect(collect($overrides['cells'])->firstWhere('session', 0)['data']['reps'] ?? null)->toBe(15);
    expect(collect($overrides['cells'])->firstWhere('session', 1)['data']['reps'] ?? null)->toBe(11);
    expect(collect($overrides['cells'])->firstWhere('session', 2)['data']['reps'] ?? null)->toBe(12);
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
