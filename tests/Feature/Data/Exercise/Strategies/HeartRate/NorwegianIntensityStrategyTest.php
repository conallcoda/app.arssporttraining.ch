<?php

use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Settings\HeartRateSetting;
use App\Data\Exercise\Strategies\HeartRate\NorwegianIntensityStrategy;
use App\Training\Reference\BikingZoneTable;
use App\Training\Reference\JoggingZoneTable;

function buildStateWithZones(array $setsPerWeek, array $zones): GridState
{
    $state = new GridState;
    $state->setSetsPerWeek($setsPerWeek);
    $state->setGrid('heartRateZone', $zones);

    return $state;
}

it('generates heart rate grid from zone grid for biking', function () {
    $setting = HeartRateSetting::from(['mode' => 'automatic_biking']);
    $setsPerWeek = [3, 3];
    $zones = [
        ['0', '1', '2'],
        ['3', '4', '1'],
    ];
    $state = buildStateWithZones($setsPerWeek, $zones);

    $strategy = new NorwegianIntensityStrategy($setting, maxHR: 190, iatPercent: 90);
    $result = $strategy->generate(2, $state);

    expect($result)->not->toBeNull();
    expect($result[0][0])->toBe(BikingZoneTable::getRangeForZoneSpec('0', 190, 90));
    expect($result[0][1])->toBe(BikingZoneTable::getRangeForZoneSpec('1', 190, 90));
    expect($result[0][2])->toBe(BikingZoneTable::getRangeForZoneSpec('2', 190, 90));
    expect($result[1][0])->toBe(BikingZoneTable::getRangeForZoneSpec('3', 190, 90));
    expect($result[1][1])->toBe(BikingZoneTable::getRangeForZoneSpec('4', 190, 90));
});

it('generates heart rate grid from zone grid for jogging', function () {
    $setting = HeartRateSetting::from(['mode' => 'automatic_jogging']);
    $setsPerWeek = [2, 2];
    $zones = [
        ['1', '3'],
        ['2', '4'],
    ];
    $state = buildStateWithZones($setsPerWeek, $zones);

    $strategy = new NorwegianIntensityStrategy($setting, maxHR: 190, iatPercent: 90);
    $result = $strategy->generate(2, $state);

    expect($result)->not->toBeNull();
    expect($result[0][0])->toBe(JoggingZoneTable::getRangeForZoneSpec('1', 190, 90));
    expect($result[1][1])->toBe(JoggingZoneTable::getRangeForZoneSpec('4', 190, 90));
});

it('falls back to default zone 2 when no zone grid exists', function () {
    $setting = HeartRateSetting::from(['mode' => 'automatic_biking']);
    $state = new GridState;
    $state->setSetsPerWeek([2, 2]);

    $strategy = new NorwegianIntensityStrategy($setting, maxHR: 190, iatPercent: 90);
    $result = $strategy->generate(2, $state);

    $expectedRange = BikingZoneTable::getRangeForZoneSpec('2', 190, 90);
    expect($result[0][0])->toBe($expectedRange);
    expect($result[1][1])->toBe($expectedRange);
});

it('returns null when mode is manual', function () {
    $setting = HeartRateSetting::from(['mode' => 'manual']);
    $state = new GridState;
    $state->setSetsPerWeek([2]);

    $strategy = new NorwegianIntensityStrategy($setting);

    expect($strategy->generate(1, $state))->toBeNull();
});

it('handles zone range values', function () {
    $setting = HeartRateSetting::from(['mode' => 'automatic_biking']);
    $setsPerWeek = [2];
    $zones = [
        ['1-3', '0-4'],
    ];
    $state = buildStateWithZones($setsPerWeek, $zones);

    $strategy = new NorwegianIntensityStrategy($setting, maxHR: 190, iatPercent: 90);
    $result = $strategy->generate(1, $state);

    expect($result[0][0])->toBe(BikingZoneTable::getRangeForZoneSpec('1-3', 190, 90));
    expect($result[0][1])->toBe(BikingZoneTable::getRangeForZoneSpec('0-4', 190, 90));
});

it('marks heartRate cells as non-editable', function () {
    $setting = HeartRateSetting::from(['mode' => 'automatic_biking']);
    $state = new GridState;
    $state->setSetsPerWeek([2]);
    $state->setGrid('heartRateZone', [['2', '2']]);

    $strategy = new NorwegianIntensityStrategy($setting);
    $strategy->generate(1, $state);
    $state->addEditabilityStrategy($strategy);

    expect($state->isCellEditable('heartRate', 0, 0))->toBeFalse();
    expect($state->isCellEditable('heartRateZone', 0, 0))->toBeTrue();
    expect($state->isCellEditable('reps', 0, 0))->toBeTrue();
});

it('writes heart rate grid to state', function () {
    $setting = HeartRateSetting::from(['mode' => 'automatic_biking']);
    $state = new GridState;
    $state->setSetsPerWeek([3, 3]);
    $state->setGrid('heartRateZone', [['2', '2', '2'], ['2', '2', '2']]);

    $strategy = new NorwegianIntensityStrategy($setting, maxHR: 190, iatPercent: 90);
    $strategy->generate(2, $state);

    expect($state->hasGrid('heartRate'))->toBeTrue();

    $grid = $state->getGrid('heartRate');
    expect($grid)->toHaveCount(2);
    expect($grid[0])->toHaveCount(3);
});
