<?php

use App\Data\Exercise\Preview\OverrideManager;

it('returns empty overrides from reset', function () {
    expect(OverrideManager::reset())->toBe(['sessions' => [], 'cells' => []]);
});

it('sets a session override when value differs from default', function () {
    $overrides = OverrideManager::reset();
    $config = ['tempo' => ['default' => '3-1-2-0']];

    $overrides = OverrideManager::updateSessionOverride($overrides, $config, 0, 1, 'tempo', '4-2-1-0');

    expect($overrides['sessions'])->toHaveCount(1);
    expect($overrides['sessions'][0])->toBe([
        'week' => 0,
        'session' => 1,
        'data' => ['tempo' => '4-2-1-0'],
    ]);
});

it('removes a session override when value matches default', function () {
    $overrides = ['sessions' => [
        ['week' => 0, 'session' => 1, 'data' => ['tempo' => '4-2-1-0']],
    ], 'cells' => []];
    $config = ['tempo' => ['default' => '3-1-2-0']];

    $overrides = OverrideManager::updateSessionOverride($overrides, $config, 0, 1, 'tempo', '3-1-2-0');

    expect($overrides['sessions'])->toBeEmpty();
});

it('updates existing session override data field', function () {
    $overrides = ['sessions' => [
        ['week' => 2, 'session' => 1, 'data' => ['tempo' => '4-2-1-0']],
    ], 'cells' => []];
    $config = ['tempo' => ['default' => '3-1-2-0'], 'rest' => ['default' => 90]];

    $overrides = OverrideManager::updateSessionOverride($overrides, $config, 2, 1, 'rest', 120);

    expect($overrides['sessions'])->toHaveCount(1);
    expect($overrides['sessions'][0]['data'])->toBe(['tempo' => '4-2-1-0', 'rest' => 120]);
});

it('removes entire session entry when last field is removed', function () {
    $overrides = ['sessions' => [
        ['week' => 1, 'session' => 0, 'data' => ['rest' => 120]],
    ], 'cells' => []];
    $config = ['rest' => ['default' => 120]];

    $overrides = OverrideManager::updateSessionOverride($overrides, $config, 1, 0, 'rest', 120);

    expect($overrides['sessions'])->toBeEmpty();
});

it('handles string comparison for tempo session overrides', function () {
    $overrides = OverrideManager::reset();
    $config = ['tempo' => ['default' => '3-1-2-0']];

    $overrides = OverrideManager::updateSessionOverride($overrides, $config, 0, 0, 'tempo', '3-1-2-0');

    expect($overrides['sessions'])->toBeEmpty();
});

it('handles string comparison for pace session overrides', function () {
    $overrides = OverrideManager::reset();
    $config = ['pace' => ['default' => '5:30']];

    $overrides = OverrideManager::updateSessionOverride($overrides, $config, 0, 0, 'pace', '5:30');

    expect($overrides['sessions'])->toBeEmpty();
});

it('handles float comparison with tolerance for numeric session overrides', function () {
    $overrides = OverrideManager::reset();
    $config = ['rest' => ['default' => 90.0]];

    $overrides = OverrideManager::updateSessionOverride($overrides, $config, 0, 0, 'rest', 90.0005);

    expect($overrides['sessions'])->toBeEmpty();
});

it('treats null default as non-matching for session overrides', function () {
    $overrides = OverrideManager::reset();
    $config = ['rest' => []];

    $overrides = OverrideManager::updateSessionOverride($overrides, $config, 0, 0, 'rest', 90);

    expect($overrides['sessions'])->toHaveCount(1);
});
