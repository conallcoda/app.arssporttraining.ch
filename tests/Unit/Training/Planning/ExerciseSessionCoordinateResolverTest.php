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

it('maps a chronological slot into its fixed group and session coordinates', function () {
    $position = app(ExerciseSessionCoordinateResolver::class)->resolve(
        effectiveConfig: [
            'preview' => [
                'groupingMode' => 'groups',
                'groupSize' => 2,
                'weeks' => 2,
            ],
        ],
        calendarWeekIndex: 1,
        calendarSessionIndex: 1,
        slotIndex: 3,
        useSlotIndexForGroupedSessions: true,
    );

    expect($position)
        ->week->toBe(1)
        ->session->toBe(1)
        ->usesGroupedSlotIndex->toBeTrue();
});

it('keeps week grouping on calendar coordinates', function () {
    $position = app(ExerciseSessionCoordinateResolver::class)->resolve(
        effectiveConfig: [
            'preview' => [
                'groupingMode' => 'week',
                'groupSize' => 2,
            ],
        ],
        calendarWeekIndex: 4,
        calendarSessionIndex: 1,
        slotIndex: 9,
        useSlotIndexForGroupedSessions: true,
    );

    expect($position)
        ->week->toBe(4)
        ->session->toBe(1)
        ->usesChronologicalSessions->toBeFalse()
        ->usesGroupedSlotIndex->toBeFalse();
});
