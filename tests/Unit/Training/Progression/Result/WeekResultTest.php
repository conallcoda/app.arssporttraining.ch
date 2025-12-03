<?php

use App\Models\Training\Progression\Result\SessionResult;
use App\Models\Training\Progression\Result\SetResult;
use App\Models\Training\Progression\Result\WeekResult;

describe('WeekResult', function () {
    beforeEach(function () {
        $this->sessions = [
            new SessionResult(
                sessionUuid: 'uuid-1',
                day: 0,
                slot: 0,
                sets: [
                    new SetResult(setIndex: 0, reps: 12, weight: 42.5, source: 'computed'),
                    new SetResult(setIndex: 1, reps: 12, weight: 45.0, source: 'computed'),
                    new SetResult(setIndex: 2, reps: 10, weight: 47.5, source: 'computed'),
                ],
            ),
            new SessionResult(
                sessionUuid: 'uuid-2',
                day: 2,
                slot: 0,
                sets: [
                    new SetResult(setIndex: 0, reps: 12, weight: 42.5, source: 'computed'),
                    new SetResult(setIndex: 1, reps: 12, weight: 45.0, source: 'computed'),
                    new SetResult(setIndex: 2, reps: 10, weight: 47.5, source: 'computed'),
                ],
            ),
        ];
    });

    it('can be created with sessions', function () {
        $result = new WeekResult(
            weekIndex: 0,
            anchorWeight: 47.5,
            anchorReps: 10,
            projected1RM: 64.2,
            reference1RM: 70.0,
            sessions: $this->sessions,
        );

        expect($result->weekIndex)->toBe(0);
        expect($result->anchorWeight)->toBe(47.5);
        expect($result->anchorReps)->toBe(10);
        expect($result->projected1RM)->toBe(64.2);
        expect($result->reference1RM)->toBe(70.0);
        expect($result->sessions)->toHaveCount(2);
    });

    it('can get session by uuid', function () {
        $result = new WeekResult(
            weekIndex: 0,
            anchorWeight: 47.5,
            anchorReps: 10,
            sessions: $this->sessions,
        );

        expect($result->getSession('uuid-1'))->not->toBeNull();
        expect($result->getSession('uuid-1')->day)->toBe(0);
        expect($result->getSession('uuid-2')->day)->toBe(2);
        expect($result->getSession('non-existent'))->toBeNull();
    });

    it('returns session count', function () {
        $result = new WeekResult(
            weekIndex: 0,
            anchorWeight: 47.5,
            anchorReps: 10,
            sessions: $this->sessions,
        );

        expect($result->sessionCount())->toBe(2);
    });

    it('detects fully locked week', function () {
        $lockedSessions = [
            new SessionResult(
                sessionUuid: 'uuid-1',
                day: 0,
                slot: 0,
                sets: [
                    new SetResult(setIndex: 0, reps: 12, weight: 42.5, source: 'locked'),
                    new SetResult(setIndex: 1, reps: 12, weight: 45.0, source: 'locked'),
                ],
            ),
            new SessionResult(
                sessionUuid: 'uuid-2',
                day: 2,
                slot: 0,
                sets: [
                    new SetResult(setIndex: 0, reps: 12, weight: 42.5, source: 'locked'),
                    new SetResult(setIndex: 1, reps: 12, weight: 45.0, source: 'locked'),
                ],
            ),
        ];

        $result = new WeekResult(
            weekIndex: 0,
            anchorWeight: 47.5,
            anchorReps: 10,
            sessions: $lockedSessions,
        );

        expect($result->isFullyLocked())->toBeTrue();
    });

    it('detects partially locked week as not fully locked', function () {
        $result = new WeekResult(
            weekIndex: 0,
            anchorWeight: 47.5,
            anchorReps: 10,
            sessions: $this->sessions,
        );

        expect($result->isFullyLocked())->toBeFalse();
    });

    it('empty week is not fully locked', function () {
        $result = new WeekResult(
            weekIndex: 0,
            anchorWeight: 47.5,
            anchorReps: 10,
            sessions: [],
        );

        expect($result->isFullyLocked())->toBeFalse();
    });

    it('detects week with overrides', function () {
        $sessionsWithOverride = [
            new SessionResult(
                sessionUuid: 'uuid-1',
                day: 0,
                slot: 0,
                sets: [
                    new SetResult(setIndex: 0, reps: 10, weight: 42.5, source: 'manual', computedReps: 12),
                ],
            ),
        ];

        $result = new WeekResult(
            weekIndex: 0,
            anchorWeight: 47.5,
            anchorReps: 10,
            sessions: $sessionsWithOverride,
        );

        expect($result->hasOverrides())->toBeTrue();
    });

    it('calculates total volume across sessions', function () {
        $result = new WeekResult(
            weekIndex: 0,
            anchorWeight: 47.5,
            anchorReps: 10,
            sessions: $this->sessions,
        );

        $sessionVolume = (12 * 42.5) + (12 * 45.0) + (10 * 47.5);
        $expectedVolume = $sessionVolume * 2;
        expect($result->getTotalVolume())->toBe($expectedVolume);
    });

    it('returns week label', function () {
        $result = new WeekResult(
            weekIndex: 0,
            anchorWeight: 47.5,
            anchorReps: 10,
            sessions: [],
        );

        expect($result->getWeekLabel())->toBe('Week 1');

        $result2 = new WeekResult(
            weekIndex: 4,
            anchorWeight: 67.5,
            anchorReps: 6,
            sessions: [],
        );

        expect($result2->getWeekLabel())->toBe('Week 5');
    });

    it('calculates derived 1RM from anchor weight and reps', function () {
        $result = new WeekResult(
            weekIndex: 0,
            anchorWeight: 55.5,
            anchorReps: 10,
            sessions: [],
        );

        expect($result->getDerived1RM())->toBe(75.0);

        $result2 = new WeekResult(
            weekIndex: 4,
            anchorWeight: 67.5,
            anchorReps: 6,
            sessions: [],
        );

        expect(round($result2->getDerived1RM(), 2))->toBe(78.49);
    });
});
