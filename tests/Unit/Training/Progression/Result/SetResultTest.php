<?php

use App\Models\Training\Progression\Result\SetResult;

describe('SetResult', function () {
    it('can be created with computed source', function () {
        $result = new SetResult(
            setIndex: 0,
            reps: 12,
            weight: 47.5,
            source: 'computed',
        );

        expect($result->setIndex)->toBe(0);
        expect($result->reps)->toBe(12);
        expect($result->weight)->toBe(47.5);
        expect($result->source)->toBe('computed');
        expect($result->computedReps)->toBeNull();
        expect($result->computedWeight)->toBeNull();
    });

    it('can be created with manual source and computed values', function () {
        $result = new SetResult(
            setIndex: 0,
            reps: 10,
            weight: 50.0,
            source: 'manual',
            computedReps: 12,
            computedWeight: 47.5,
        );

        expect($result->reps)->toBe(10);
        expect($result->weight)->toBe(50.0);
        expect($result->computedReps)->toBe(12);
        expect($result->computedWeight)->toBe(47.5);
    });

    it('detects computed source', function () {
        $result = new SetResult(setIndex: 0, reps: 12, weight: 47.5, source: 'computed');

        expect($result->isComputed())->toBeTrue();
        expect($result->isManual())->toBeFalse();
        expect($result->isLocked())->toBeFalse();
        expect($result->isOverridden())->toBeFalse();
    });

    it('detects manual source', function () {
        $result = new SetResult(setIndex: 0, reps: 10, weight: 50.0, source: 'manual');

        expect($result->isComputed())->toBeFalse();
        expect($result->isManual())->toBeTrue();
        expect($result->isLocked())->toBeFalse();
        expect($result->isOverridden())->toBeTrue();
    });

    it('detects locked source', function () {
        $result = new SetResult(setIndex: 0, reps: 10, weight: 50.0, source: 'locked');

        expect($result->isComputed())->toBeFalse();
        expect($result->isManual())->toBeFalse();
        expect($result->isLocked())->toBeTrue();
        expect($result->isOverridden())->toBeTrue();
    });

    it('detects rep override', function () {
        $result = new SetResult(
            setIndex: 0,
            reps: 10,
            weight: 47.5,
            source: 'manual',
            computedReps: 12,
            computedWeight: 47.5,
        );

        expect($result->hasRepOverride())->toBeTrue();
        expect($result->hasWeightOverride())->toBeFalse();
    });

    it('detects weight override', function () {
        $result = new SetResult(
            setIndex: 0,
            reps: 12,
            weight: 50.0,
            source: 'manual',
            computedReps: 12,
            computedWeight: 47.5,
        );

        expect($result->hasRepOverride())->toBeFalse();
        expect($result->hasWeightOverride())->toBeTrue();
    });

    it('calculates rep difference', function () {
        $result = new SetResult(
            setIndex: 0,
            reps: 10,
            weight: 47.5,
            source: 'manual',
            computedReps: 12,
            computedWeight: 47.5,
        );

        expect($result->getRepDifference())->toBe(-2);
    });

    it('calculates weight difference', function () {
        $result = new SetResult(
            setIndex: 0,
            reps: 12,
            weight: 50.0,
            source: 'manual',
            computedReps: 12,
            computedWeight: 47.5,
        );

        expect($result->getWeightDifference())->toBe(2.5);
    });

    it('returns null difference when computed values not set', function () {
        $result = new SetResult(setIndex: 0, reps: 12, weight: 47.5, source: 'computed');

        expect($result->getRepDifference())->toBeNull();
        expect($result->getWeightDifference())->toBeNull();
    });
});
