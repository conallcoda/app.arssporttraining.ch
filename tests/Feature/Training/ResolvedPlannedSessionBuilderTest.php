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
