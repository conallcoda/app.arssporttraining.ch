<?php

use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Training\Config\ExerciseOverrides;
use App\Training\Planning\ResolvedPlannedSessionBuilder;

it('builds a resolved planned exercise with session values and week fields', function () {
    $builder = app(ResolvedPlannedSessionBuilder::class);

    $exercise = $builder->buildExercise(
        exerciseId: 12,
        sort: 0,
        group: 'A1',
        type: 'main',
        effectiveConfig: [
            'settings' => ['reps', 'rest'],
            'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
            'rest' => ['default' => 60, 'applyPer' => 'week'],
        ],
        overrideLayer: [
            'sessions' => [
                ['week' => 0, 'session' => 1, 'data' => ['rest' => 90]],
            ],
            'cells' => [
                ['week' => 0, 'session' => 1, 'set' => 0, 'data' => ['reps' => 10]],
            ],
        ],
        weekIndex: 0,
        sessionIndex: 1,
        weeks: 1,
        sessionCounts: [2],
    );

    expect($exercise)->not->toBeNull()
        ->and($exercise->exerciseId)->toBe(12)
        ->and($exercise->sets)->toHaveCount(2)
        ->and($exercise->sets[0]->values[0]->settingKey)->toBe('reps')
        ->and($exercise->sets[0]->values[0]->value)->toBe(10)
        ->and($exercise->sets[0]->values[0]->provenance?->kind)->toBe('grid_override')
        ->and($exercise->sets[0]->values[0]->provenance?->layer)->toBe('exercise')
        ->and($exercise->sets[1]->values[0]->value)->toBe(8)
        ->and(collect($exercise->sets[0]->values)->firstWhere('settingKey', 'rest')?->value)->toBe(90)
        ->and(collect($exercise->sets[0]->values)->firstWhere('settingKey', 'rest')?->provenance?->kind)->toBe('grid_override');
});

it('builds grouped automatic weight progression through the shared planned model', function () {
    $builder = app(ResolvedPlannedSessionBuilder::class);

    $exercise = $builder->buildExercise(
        exerciseId: 12,
        sort: 0,
        group: null,
        type: 'main',
        effectiveConfig: [
            'settings' => ['reps', 'weight'],
            'preview' => ['groupingMode' => 'groups', 'groupSize' => 2, 'sessionsPerWeek' => 2],
            'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
            'reps' => [
                'mode' => 'automatic',
                'default' => 10,
                'stepDownInterval' => 1,
                'decrement' => 2,
                'minimum' => 1,
                'applyPer' => 'session',
            ],
            'weight' => ['mode' => 'automatic', 'oneRepMaxModifier' => 100, 'applyPer' => 'session'],
        ],
        overrideLayer: ['sessions' => [], 'cells' => []],
        weekIndex: 1,
        sessionIndex: 0,
        weeks: 3,
        sessionCounts: [2, 2, 2],
        measuredData: new WeightProgressionSetting(measuredReps: 10, measuredWeight: 52, targetGoal: 7),
    );

    expect($exercise)->not->toBeNull()
        ->and(collect($exercise->sets[0]->values)->firstWhere('settingKey', 'reps')?->value)->toBe(8)
        ->and(collect($exercise->sets[0]->values)->firstWhere('settingKey', 'reps')?->provenance?->kind)->toBe('strategy')
        ->and(collect($exercise->sets[0]->values)->firstWhere('settingKey', 'weight')?->value)->toBeFloat()
        ->and(collect($exercise->sets[0]->values)->firstWhere('settingKey', 'weight')?->provenance?->kind)->toBe('strategy')
        ->and($exercise->setCountProvenance?->kind)->toBe('strategy');
});

it('resolves grouped progression from the persisted athlete slot index', function () {
    $builder = app(ResolvedPlannedSessionBuilder::class);

    $session = $builder->build(
        weekIndex: 0,
        sessionIndex: 0,
        scheduledDate: '2026-02-25',
        exerciseConfigs: [[
            'exerciseId' => 12,
            'sort' => 0,
            'group' => null,
            'type' => 'main',
            'effectiveConfig' => [
                'settings' => ['reps'],
                'preview' => ['groupingMode' => 'groups', 'groupSize' => 2],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => [
                    'mode' => 'automatic',
                    'default' => 10,
                    'stepDownInterval' => 1,
                    'decrement' => 2,
                    'minimum' => 1,
                    'applyPer' => 'session',
                ],
            ],
            'overrideLayer' => ['sessions' => [], 'cells' => []],
        ]],
        weeks: 1,
        sessionCounts: [1],
        slotIndex: 2,
        useSlotIndexForGroupedSessions: true,
    );

    expect($session->weekIndex)->toBe(0)
        ->and($session->exercises[0]->sets[0]->values[0]->value)->toBe(8);
});

it('resolves grouped manual overrides by group and session from the chronological slot index', function () {
    $builder = app(ResolvedPlannedSessionBuilder::class);
    $exerciseConfig = [
        'exerciseId' => 12,
        'sort' => 0,
        'group' => null,
        'type' => 'main',
        'effectiveConfig' => [
            'settings' => ['reps'],
            'preview' => ['groupingMode' => 'groups', 'groupSize' => 2],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'per_set'],
        ],
        'overrideLayer' => [
            'sessions' => [],
            'cells' => [
                ['week' => 2, 'session' => 0, 'set' => 0, 'data' => ['reps' => 8]],
                ['week' => 2, 'session' => 1, 'set' => 0, 'data' => ['reps' => 8]],
                ['week' => 3, 'session' => 0, 'set' => 0, 'data' => ['reps' => 8]],
                ['week' => 3, 'session' => 1, 'set' => 0, 'data' => ['reps' => 8]],
                ['week' => 4, 'session' => 0, 'set' => 0, 'data' => ['reps' => 6]],
                ['week' => 4, 'session' => 1, 'set' => 0, 'data' => ['reps' => 6]],
            ],
        ],
    ];

    $reps = collect(range(0, 9))->map(function (int $slotIndex) use ($builder, $exerciseConfig): int {
        $session = $builder->build(
            weekIndex: intdiv($slotIndex, 2),
            sessionIndex: $slotIndex % 2,
            scheduledDate: '2026-08-31',
            exerciseConfigs: [$exerciseConfig],
            weeks: 5,
            sessionCounts: [2, 2, 2, 2, 2],
            slotIndex: $slotIndex,
            useSlotIndexForGroupedSessions: true,
            plannedWeekCount: 5,
        );

        return (int) $session->exercises[0]->sets[0]->values[0]->value;
    })->all();

    expect($reps)->toBe([10, 10, 10, 10, 8, 8, 8, 8, 6, 6]);
});

it('uses the authored grouped session total before every slot is scheduled', function () {
    $builder = app(ResolvedPlannedSessionBuilder::class);
    $exerciseConfigs = [[
        'exerciseId' => 12,
        'sort' => 0,
        'group' => null,
        'type' => 'main',
        'effectiveConfig' => [
            'settings' => ['reps', 'weight'],
            'preview' => ['groupingMode' => 'groups', 'groupSize' => 2],
            'sets' => ['default' => 3, 'label' => 'Set', 'deload' => 'none'],
            'reps' => [
                'mode' => 'automatic',
                'default' => 7,
                'stepDownInterval' => 2,
                'decrement' => 2,
                'minimum' => 1,
                'applyPer' => 'session',
            ],
            'weight' => ['mode' => 'automatic', 'oneRepMaxModifier' => 85, 'applyPer' => 'session'],
        ],
        'overrideLayer' => ['sessions' => [], 'cells' => []],
        'measuredData' => new WeightProgressionSetting(measuredReps: 1, measuredWeight: 117.5, targetGoal: 3),
    ]];

    $buildFirstSession = function (array $sessionCounts) use ($builder, $exerciseConfigs): array {
        $session = $builder->build(
            weekIndex: 0,
            sessionIndex: 0,
            scheduledDate: '2026-08-21',
            exerciseConfigs: $exerciseConfigs,
            weeks: 1,
            sessionCounts: $sessionCounts,
            slotIndex: 0,
            useSlotIndexForGroupedSessions: true,
            plannedWeekCount: 4,
        );

        return collect($session->exercises[0]->sets)
            ->map(fn ($set): float => (float) collect($set->values)->firstWhere('settingKey', 'weight')->value)
            ->all();
    };

    $firstSlotOnly = $buildFirstSession([1]);
    $allEightSlots = $buildFirstSession(array_fill(0, 8, 1));

    expect($firstSlotOnly)->toBe([71.5, 76.5, 81.5])
        ->and($firstSlotOnly)->toBe($allEightSlots);
});

it('tracks plan and user provenance layers distinctly', function () {
    $builder = app(ResolvedPlannedSessionBuilder::class);

    $exercise = $builder->buildExercise(
        exerciseId: 12,
        sort: 0,
        group: null,
        type: 'main',
        effectiveConfig: [
            'settings' => ['reps', 'rest'],
            'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
            'rest' => ['default' => 75, 'applyPer' => 'week'],
        ],
        overrideLayer: ['sessions' => [], 'cells' => []],
        weekIndex: 0,
        sessionIndex: 0,
        weeks: 1,
        sessionCounts: [1],
        baseConfig: [
            'settings' => ['reps', 'rest'],
            'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
            'rest' => ['default' => 60, 'applyPer' => 'week'],
        ],
        defaultOverrides: ExerciseOverrides::from([
            'rest' => ['default' => 75, 'applyPer' => 'week'],
        ]),
        userOverrides: ExerciseOverrides::from([
            'gridOverrides' => [
                'cells' => [
                    ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 10]],
                ],
                'sessions' => [],
            ],
        ]),
    );

    expect(collect($exercise->sets[0]->values)->firstWhere('settingKey', 'rest')?->provenance?->layer)->toBe('plan')
        ->and(collect($exercise->sets[0]->values)->firstWhere('settingKey', 'rest')?->provenance?->kind)->toBe('config')
        ->and(collect($exercise->sets[0]->values)->firstWhere('settingKey', 'reps')?->provenance?->layer)->toBe('user')
        ->and(collect($exercise->sets[0]->values)->firstWhere('settingKey', 'reps')?->provenance?->kind)->toBe('grid_override');
});
