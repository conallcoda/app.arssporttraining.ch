<?php

use App\Support\Training\WeekSessionCountResolver;

it('prefers explicit dated and locked sessions over the fallback count', function () {
    expect(WeekSessionCountResolver::resolveForWeek(
        weekIndex: 0,
        fallbackSessionsPerWeek: 4,
        weekSessions: [1],
        weekSessionDates: [['2030-04-01', '2030-04-03']],
        lockedSessionsByWeek: [[true, true, true]],
    ))->toBe(3);
});

it('falls back to the configured sessions per week when no concrete schedule exists', function () {
    expect(WeekSessionCountResolver::resolveForWeek(
        weekIndex: 2,
        fallbackSessionsPerWeek: 4,
        weekSessions: [],
        weekSessionDates: [],
        lockedSessionsByWeek: [],
    ))->toBe(4);
});
