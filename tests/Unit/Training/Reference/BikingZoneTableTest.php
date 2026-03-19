<?php

use App\Training\Reference\BikingZoneTable;

describe('BikingZoneTable', function () {
    it('returns correct range for each zone with default IAT', function () {
        $maxHR = 190;

        expect(BikingZoneTable::getRange(0, $maxHR))->toBe(['lower' => 95, 'upper' => 123]);
        expect(BikingZoneTable::getRange(1, $maxHR))->toBe(['lower' => 124, 'upper' => 142]);
        expect(BikingZoneTable::getRange(2, $maxHR))->toBe(['lower' => 152, 'upper' => 170]);
        expect(BikingZoneTable::getRange(3, $maxHR))->toBe(['lower' => 171, 'upper' => 180]);
        expect(BikingZoneTable::getRange(4, $maxHR))->toBe(['lower' => 181, 'upper' => 190]);
    });

    it('uses IAT for zone 2 upper bound', function () {
        $maxHR = 200;

        expect(BikingZoneTable::getRange(2, $maxHR, 85))->toBe(['lower' => 160, 'upper' => 169]);
        expect(BikingZoneTable::getRange(2, $maxHR, 90))->toBe(['lower' => 160, 'upper' => 179]);
        expect(BikingZoneTable::getRange(2, $maxHR, 95))->toBe(['lower' => 160, 'upper' => 189]);
    });

    it('clamps zone below 0 to 0', function () {
        expect(BikingZoneTable::getRange(-1, 190))->toBe(BikingZoneTable::getRange(0, 190));
    });

    it('clamps zone above 4 to 4', function () {
        expect(BikingZoneTable::getRange(5, 190))->toBe(BikingZoneTable::getRange(4, 190));
    });

    it('returns range string for single zone spec', function () {
        expect(BikingZoneTable::getRangeForZoneSpec('2', 190))->toBe('152-170');
    });

    it('returns range string spanning multiple zones', function () {
        $result = BikingZoneTable::getRangeForZoneSpec('1-3', 190);

        expect($result)->toBe('124-180');
    });

    it('returns full range for 0-4 spec', function () {
        $result = BikingZoneTable::getRangeForZoneSpec('0-4', 190);

        expect($result)->toBe('95-190');
    });

    it('rounds BPM values to integers', function () {
        $range = BikingZoneTable::getRange(0, 193);

        expect($range['lower'])->toBeInt();
        expect($range['upper'])->toBeInt();
    });

    it('returns the full table', function () {
        $table = BikingZoneTable::getTable();

        expect($table)->toBeArray();
        expect($table)->toHaveCount(5);
    });
});
