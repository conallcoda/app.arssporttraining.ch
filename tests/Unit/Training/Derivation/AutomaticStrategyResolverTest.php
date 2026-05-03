<?php

use App\Data\Exercise\Settings\HeartRateSetting;
use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Exercise\Settings\WeightSetting;
use App\Training\Derivation\AutomaticHeartRateResolver;
use App\Training\Derivation\AutomaticRepsResolver;
use App\Training\Derivation\AutomaticWeightResolver;
use App\Training\Reference\BikingZoneTable;
use App\Training\Reference\JoggingZoneTable;

uses(Tests\TestCase::class);

it('resolves automatic heart rate ranges by mode and zone', function () {
    $resolver = new AutomaticHeartRateResolver;

    expect($resolver->resolveRange('automatic_biking', '2', 190, 90))->toBe(BikingZoneTable::getRangeForZoneSpec('2', 190, 90))
        ->and($resolver->resolveRange('automatic_jogging', '2', 190, 90))->toBe(JoggingZoneTable::getRangeForZoneSpec('2', 190, 90))
        ->and($resolver->resolveRange('manual', '2', 190, 90))->toBeNull();
});

it('builds automatic heart rate strategy resolution with colors', function () {
    $resolver = new AutomaticHeartRateResolver;
    $setting = HeartRateSetting::from(['mode' => 'automatic_biking']);

    $resolution = $resolver->resolve(
        $setting,
        1,
        [2],
        fn (int $week, int $set, ?int $session): string => $session === null ? ['1', '2'][$set] : ['3', '4'][$set],
        fn (int $week, int $set, ?int $session): bool => $session !== null,
        [
            ['week' => 0, 'session' => 1, 'set' => 0],
            ['week' => 0, 'session' => 1, 'set' => 1],
        ],
        190,
        90,
    );

    expect($resolution)->not->toBeNull();

    $field = $resolution?->field('heartRate');

    expect($field)->not->toBeNull()
        ->and($field?->grid[0][0])->toBe(BikingZoneTable::getRangeForZoneSpec('1', 190, 90))
        ->and($field?->sessionGrid[0][1][1])->toBe(BikingZoneTable::getRangeForZoneSpec('4', 190, 90))
        ->and($field?->cellColorGrid[0][0])->toBeString()
        ->and($field?->sessionCellOverrideColorGrid[0][1][0])->toBeString();
});

it('builds automatic reps grid from the shared resolver', function () {
    $resolver = new AutomaticRepsResolver;
    $setting = RepsSetting::from([
        'mode' => 'automatic',
        'default' => '12_12',
        'stepDownInterval' => 2,
        'decrement' => 2,
        'minimum' => 1,
    ]);

    $grid = $resolver->buildGrid($setting, 3, [4, 4, 4]);

    expect($grid[0][0])->toBe('12_12')
        ->and($grid[0][3])->toBe('10_10')
        ->and($grid[2][0])->toBe('10_10');
});

it('builds automatic reps strategy resolution from the shared resolver', function () {
    $resolver = new AutomaticRepsResolver;
    $setting = RepsSetting::from([
        'mode' => 'automatic',
        'default' => 10,
        'stepDownInterval' => 2,
        'decrement' => 2,
        'minimum' => 1,
    ]);

    $resolution = $resolver->resolve($setting, 2, [3, 3]);

    expect($resolution->field('reps')?->grid)->toBe([
        [10, 10, 8],
        [10, 10, 8],
    ]);
});

it('builds automatic weight grids and summary from the shared resolver', function () {
    $resolver = new AutomaticWeightResolver;
    $setting = WeightSetting::from([
        'mode' => 'automatic',
        'oneRepMaxModifier' => 100,
    ]);
    $measuredData = new WeightProgressionSetting(
        measuredReps: 10,
        measuredWeight: 52,
        targetGoal: 7,
    );

    $resolution = $resolver->buildResolution(
        $setting,
        $measuredData,
        5,
        [3, 3, 3, 3, 3],
        fn (int $week, int $set): int => 8,
    );

    expect($resolution)->not->toBeNull()
        ->and($resolution->weights[0][0])->toBeFloat()
        ->and($resolution->oneRepMax[4][2])->toBe('-')
        ->and($resolution->oneRepMaxSessionGrid[4][0][2])->toBeFloat()
        ->and($resolution->summary['starting1RM'])->toBeFloat()
        ->and($resolution->summary['target1RM'])->toBeFloat();
});

it('builds automatic weight strategy resolution from the shared resolver', function () {
    $resolver = new AutomaticWeightResolver;
    $setting = WeightSetting::from([
        'mode' => 'automatic',
        'oneRepMaxModifier' => 100,
    ]);
    $measuredData = new WeightProgressionSetting(
        measuredReps: 10,
        measuredWeight: 52,
        targetGoal: 7,
    );

    $resolution = $resolver->resolve(
        $setting,
        $measuredData,
        3,
        [3, 3, 3],
        fn (int $week, int $set): int => 8,
    );

    expect($resolution)->not->toBeNull()
        ->and($resolution?->field('weight')?->grid[0][0])->toBeFloat()
        ->and($resolution?->field('weight')?->metadata['summary']['targetGoal'])->toBe(7)
        ->and($resolution?->field('oneRepMax')?->grid[2][2])->toBe('-')
        ->and($resolution?->field('oneRepMax')?->sessionGrid[2][0][2])->toBeFloat();
});
