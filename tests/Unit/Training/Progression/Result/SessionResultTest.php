<?php

use App\Models\Training\Progression\Result\SessionResult;
use App\Models\Training\Progression\Result\SetResult;

describe('SessionResult', function () {
    beforeEach(function () {
        $this->sets = [
            new SetResult(setIndex: 0, reps: 12, weight: 42.5, source: 'computed'),
            new SetResult(setIndex: 1, reps: 12, weight: 45.0, source: 'computed'),
            new SetResult(setIndex: 2, reps: 10, weight: 47.5, source: 'computed'),
        ];
    });

    it('can be created with sets', function () {
        $result = new SessionResult(
            sessionUuid: 'uuid-123',
            day: 0,
            slot: 0,
            sets: $this->sets,
        );

        expect($result->sessionUuid)->toBe('uuid-123');
        expect($result->day)->toBe(0);
        expect($result->slot)->toBe(0);
        expect($result->sets)->toHaveCount(3);
    });

    it('can get set by index', function () {
        $result = new SessionResult(
            sessionUuid: 'uuid-123',
            day: 0,
            slot: 0,
            sets: $this->sets,
        );

        expect($result->getSet(0)->reps)->toBe(12);
        expect($result->getSet(2)->reps)->toBe(10);
        expect($result->getSet(5))->toBeNull();
    });

    it('returns set count', function () {
        $result = new SessionResult(
            sessionUuid: 'uuid-123',
            day: 0,
            slot: 0,
            sets: $this->sets,
        );

        expect($result->setCount())->toBe(3);
    });

    it('detects fully locked session', function () {
        $lockedSets = [
            new SetResult(setIndex: 0, reps: 12, weight: 42.5, source: 'locked'),
            new SetResult(setIndex: 1, reps: 12, weight: 45.0, source: 'locked'),
            new SetResult(setIndex: 2, reps: 10, weight: 47.5, source: 'locked'),
        ];

        $result = new SessionResult(
            sessionUuid: 'uuid-123',
            day: 0,
            slot: 0,
            sets: $lockedSets,
        );

        expect($result->isFullyLocked())->toBeTrue();
    });

    it('detects partially locked session as not fully locked', function () {
        $mixedSets = [
            new SetResult(setIndex: 0, reps: 12, weight: 42.5, source: 'locked'),
            new SetResult(setIndex: 1, reps: 12, weight: 45.0, source: 'computed'),
            new SetResult(setIndex: 2, reps: 10, weight: 47.5, source: 'locked'),
        ];

        $result = new SessionResult(
            sessionUuid: 'uuid-123',
            day: 0,
            slot: 0,
            sets: $mixedSets,
        );

        expect($result->isFullyLocked())->toBeFalse();
    });

    it('empty session is not fully locked', function () {
        $result = new SessionResult(
            sessionUuid: 'uuid-123',
            day: 0,
            slot: 0,
            sets: [],
        );

        expect($result->isFullyLocked())->toBeFalse();
    });

    it('detects session with overrides', function () {
        $setsWithOverride = [
            new SetResult(setIndex: 0, reps: 12, weight: 42.5, source: 'computed'),
            new SetResult(setIndex: 1, reps: 10, weight: 45.0, source: 'manual', computedReps: 12),
            new SetResult(setIndex: 2, reps: 10, weight: 47.5, source: 'computed'),
        ];

        $result = new SessionResult(
            sessionUuid: 'uuid-123',
            day: 0,
            slot: 0,
            sets: $setsWithOverride,
        );

        expect($result->hasOverrides())->toBeTrue();
    });

    it('detects session without overrides', function () {
        $result = new SessionResult(
            sessionUuid: 'uuid-123',
            day: 0,
            slot: 0,
            sets: $this->sets,
        );

        expect($result->hasOverrides())->toBeFalse();
    });

    it('calculates total volume', function () {
        $result = new SessionResult(
            sessionUuid: 'uuid-123',
            day: 0,
            slot: 0,
            sets: $this->sets,
        );

        $expectedVolume = (12 * 42.5) + (12 * 45.0) + (10 * 47.5);
        expect($result->getTotalVolume())->toBe($expectedVolume);
    });
});
