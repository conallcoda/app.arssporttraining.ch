<?php

use App\Data\Exercise\Preview\OverrideManager;

it('returns empty overrides from reset', function () {
    expect(OverrideManager::reset())->toBe(['cells' => [], 'weeks' => []]);
});

it('sets a week override when value differs from default', function () {
    $overrides = OverrideManager::reset();
    $config = ['tempo' => ['default' => '3-1-2-0']];

    $overrides = OverrideManager::updateWeekOverride($overrides, $config, 0, 'tempo', '4-2-1-0');

    expect($overrides['weeks'])->toHaveCount(1);
    expect($overrides['weeks'][0])->toBe([
        'week' => 0,
        'data' => ['tempo' => '4-2-1-0'],
    ]);
});

it('removes a week override when value matches default', function () {
    $overrides = ['cells' => [], 'weeks' => [
        ['week' => 0, 'data' => ['tempo' => '4-2-1-0']],
    ]];
    $config = ['tempo' => ['default' => '3-1-2-0']];

    $overrides = OverrideManager::updateWeekOverride($overrides, $config, 0, 'tempo', '3-1-2-0');

    expect($overrides['weeks'])->toBeEmpty();
});

it('updates existing week override data field', function () {
    $overrides = ['cells' => [], 'weeks' => [
        ['week' => 2, 'data' => ['tempo' => '4-2-1-0']],
    ]];
    $config = ['tempo' => ['default' => '3-1-2-0'], 'rest' => ['default' => 90]];

    $overrides = OverrideManager::updateWeekOverride($overrides, $config, 2, 'rest', 120);

    expect($overrides['weeks'])->toHaveCount(1);
    expect($overrides['weeks'][0]['data'])->toBe(['tempo' => '4-2-1-0', 'rest' => 120]);
});

it('removes entire week entry when last field is removed', function () {
    $overrides = ['cells' => [], 'weeks' => [
        ['week' => 1, 'data' => ['rest' => 120]],
    ]];
    $config = ['rest' => ['default' => 120]];

    $overrides = OverrideManager::updateWeekOverride($overrides, $config, 1, 'rest', 120);

    expect($overrides['weeks'])->toBeEmpty();
});

it('handles string comparison for tempo week overrides', function () {
    $overrides = OverrideManager::reset();
    $config = ['tempo' => ['default' => '3-1-2-0']];

    $overrides = OverrideManager::updateWeekOverride($overrides, $config, 0, 'tempo', '3-1-2-0');

    expect($overrides['weeks'])->toBeEmpty();
});

it('handles string comparison for pace week overrides', function () {
    $overrides = OverrideManager::reset();
    $config = ['pace' => ['default' => '5:30']];

    $overrides = OverrideManager::updateWeekOverride($overrides, $config, 0, 'pace', '5:30');

    expect($overrides['weeks'])->toBeEmpty();
});

it('handles float comparison with tolerance for numeric week overrides', function () {
    $overrides = OverrideManager::reset();
    $config = ['rest' => ['default' => 90.0]];

    $overrides = OverrideManager::updateWeekOverride($overrides, $config, 0, 'rest', 90.0005);

    expect($overrides['weeks'])->toBeEmpty();
});

it('treats null default as non-matching for week overrides', function () {
    $overrides = OverrideManager::reset();
    $config = ['rest' => []];

    $overrides = OverrideManager::updateWeekOverride($overrides, $config, 0, 'rest', 90);

    expect($overrides['weeks'])->toHaveCount(1);
});
