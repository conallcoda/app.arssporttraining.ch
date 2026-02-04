<?php

use App\Training\Reference\RepPercentageTable;

describe('RepPercentageTable', function () {
    it('returns correct percentage for each rep count', function () {
        expect(RepPercentageTable::getPercentage(1))->toBe(1.00);
        expect(RepPercentageTable::getPercentage(6))->toBe(0.86);
        expect(RepPercentageTable::getPercentage(8))->toBe(0.80);
        expect(RepPercentageTable::getPercentage(10))->toBe(0.74);
        expect(RepPercentageTable::getPercentage(12))->toBe(0.67);
        expect(RepPercentageTable::getPercentage(14))->toBe(0.63);
        expect(RepPercentageTable::getPercentage(15))->toBe(0.62);
    });

    it('clamps reps below 1 to 1', function () {
        expect(RepPercentageTable::getPercentage(0))->toBe(1.00);
        expect(RepPercentageTable::getPercentage(-5))->toBe(1.00);
    });

    it('clamps reps above 15 to 15', function () {
        expect(RepPercentageTable::getPercentage(16))->toBe(0.62);
        expect(RepPercentageTable::getPercentage(100))->toBe(0.62);
    });

    it('returns closest rep count for a given percentage', function () {
        expect(RepPercentageTable::getReps(1.00))->toBe(1);
        expect(RepPercentageTable::getReps(0.86))->toBe(6);
        expect(RepPercentageTable::getReps(0.80))->toBe(8);
        expect(RepPercentageTable::getReps(0.74))->toBe(10);
    });

    it('returns rounded reps within brackets', function () {
        expect(RepPercentageTable::getRepsRounded(0.85, 2))->toBe(6);
        expect(RepPercentageTable::getRepsRounded(0.78, 2))->toBe(8);
        expect(RepPercentageTable::getRepsRounded(0.72, 2))->toBe(10);
        expect(RepPercentageTable::getRepsRounded(0.66, 2))->toBe(12);
        expect(RepPercentageTable::getRepsRounded(0.62, 2))->toBe(14);
    });

    it('returns the full table', function () {
        $table = RepPercentageTable::getTable();

        expect($table)->toBeArray();
        expect($table)->toHaveCount(15);
        expect($table[1])->toBe(1.00);
        expect($table[15])->toBe(0.62);
    });
});
