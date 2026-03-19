<?php

use App\Training\Reference\JoggingZoneTable;

describe('JoggingZoneTable', function () {
    it('returns correct range for each zone with default IAT', function () {
        $maxHR = 190;

        expect(JoggingZoneTable::getRange(0, $maxHR))->toBe(['lower' => 105, 'upper' => 133]);
        expect(JoggingZoneTable::getRange(1, $maxHR))->toBe(['lower' => 133, 'upper' => 152]);
        expect(JoggingZoneTable::getRange(2, $maxHR))->toBe(['lower' => 162, 'upper' => 181]);
        expect(JoggingZoneTable::getRange(3, $maxHR))->toBe(['lower' => 181, 'upper' => 190]);
        expect(JoggingZoneTable::getRange(4, $maxHR))->toBe(['lower' => 190, 'upper' => 200]);
    });

    it('uses IAT + 5 for zone 2 upper bound', function () {
        $maxHR = 200;

        expect(JoggingZoneTable::getRange(2, $maxHR, 85))->toBe(['lower' => 170, 'upper' => 180]);
        expect(JoggingZoneTable::getRange(2, $maxHR, 90))->toBe(['lower' => 170, 'upper' => 190]);
        expect(JoggingZoneTable::getRange(2, $maxHR, 95))->toBe(['lower' => 170, 'upper' => 200]);
    });

    it('clamps zone below 0 to 0', function () {
        expect(JoggingZoneTable::getRange(-1, 190))->toBe(JoggingZoneTable::getRange(0, 190));
    });

    it('clamps zone above 4 to 4', function () {
        expect(JoggingZoneTable::getRange(5, 190))->toBe(JoggingZoneTable::getRange(4, 190));
    });

    it('returns range string for single zone spec', function () {
        expect(JoggingZoneTable::getRangeForZoneSpec('2', 190))->toBe('162-181');
    });

    it('returns range string spanning multiple zones', function () {
        $result = JoggingZoneTable::getRangeForZoneSpec('1-3', 190);

        expect($result)->toBe('133-190');
    });

    it('allows BPM to exceed max HR for zone MAX', function () {
        $range = JoggingZoneTable::getRange(4, 190);

        expect($range['upper'])->toBe(200);
    });

    it('rounds BPM values to integers', function () {
        $range = JoggingZoneTable::getRange(0, 193);

        expect($range['lower'])->toBeInt();
        expect($range['upper'])->toBeInt();
    });

    it('returns the full table', function () {
        $table = JoggingZoneTable::getTable();

        expect($table)->toBeArray();
        expect($table)->toHaveCount(5);
    });
});
