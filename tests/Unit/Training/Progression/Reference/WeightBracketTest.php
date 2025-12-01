<?php

use App\Models\Training\Progression\Reference\WeightBracket;

describe('WeightBracket', function () {
    it('returns 2.5kg step for weights below 50kg', function () {
        expect(WeightBracket::getStepForWeight(30.0))->toBe(2.5);
        expect(WeightBracket::getStepForWeight(49.9))->toBe(2.5);
        expect(WeightBracket::getStepForWeight(0.0))->toBe(2.5);
    });

    it('returns 5.0kg step for weights between 50kg and 100kg', function () {
        expect(WeightBracket::getStepForWeight(50.0))->toBe(5.0);
        expect(WeightBracket::getStepForWeight(75.0))->toBe(5.0);
        expect(WeightBracket::getStepForWeight(100.0))->toBe(5.0);
    });

    it('returns 7.5kg step for weights above 100kg', function () {
        expect(WeightBracket::getStepForWeight(100.1))->toBe(7.5);
        expect(WeightBracket::getStepForWeight(150.0))->toBe(7.5);
        expect(WeightBracket::getStepForWeight(200.0))->toBe(7.5);
    });

    it('rounds weights to increment', function () {
        expect(WeightBracket::roundToIncrement(67.3, 0.5))->toBe(67.5);
        expect(WeightBracket::roundToIncrement(67.2, 0.5))->toBe(67.0);
        expect(WeightBracket::roundToIncrement(67.25, 0.5))->toBe(67.5);
    });

    it('rounds weights to 2.5kg increment', function () {
        expect(WeightBracket::roundToIncrement(67.3, 2.5))->toBe(67.5);
        expect(WeightBracket::roundToIncrement(66.0, 2.5))->toBe(65.0);
        expect(WeightBracket::roundToIncrement(66.3, 2.5))->toBe(67.5);
    });
});
