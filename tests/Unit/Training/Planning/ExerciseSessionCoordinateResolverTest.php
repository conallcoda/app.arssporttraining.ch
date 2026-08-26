<?php

use App\Training\Planning\ExerciseSessionCoordinateResolver;

it('keeps ungrouped one rep max progression on chronological session coordinates', function () {
    $position = app(ExerciseSessionCoordinateResolver::class)->resolve(
        effectiveConfig: [
            'preview' => [
                'groupingMode' => 'none',
                'weeks' => 2,
            ],
            'settings' => ['oneRepMax'],
        ],
        calendarWeekIndex: 1,
        calendarSessionIndex: 1,
        slotIndex: 3,
    );

    expect($position)
        ->week->toBe(3)
        ->session->toBe(0)
        ->usesChronologicalSessions->toBeTrue();
});

it('only wraps a finite ungrouped manual template', function () {
    $position = app(ExerciseSessionCoordinateResolver::class)->resolve(
        effectiveConfig: [
            'preview' => [
                'groupingMode' => 'none',
                'weeks' => 2,
            ],
            'settings' => ['weight'],
            'weight' => ['mode' => 'manual'],
        ],
        calendarWeekIndex: 1,
        calendarSessionIndex: 1,
        slotIndex: 3,
    );

    expect($position)
        ->week->toBe(1)
        ->session->toBe(0);
});

it('maps fixed groups to the compiler slot index when persisting carry over values', function () {
    $position = app(ExerciseSessionCoordinateResolver::class)->resolve(
        effectiveConfig: [
            'preview' => [
                'groupingMode' => 'groups',
                'weeks' => 2,
            ],
        ],
        calendarWeekIndex: 1,
        calendarSessionIndex: 1,
        slotIndex: 3,
        useSlotIndexForGroupedSessions: true,
    );

    expect($position)
        ->week->toBe(3)
        ->session->toBe(0)
        ->usesGroupedSlotIndex->toBeTrue();
});
