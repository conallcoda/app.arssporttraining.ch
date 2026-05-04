<?php

use App\Data\Exercise\ExerciseConfig;
use App\Data\Training\Config\ExerciseOverrides;
use App\Data\Training\Config\ExercisePlanConfig;
use Tests\TestCase;

uses(TestCase::class);

it('removes athlete-specific overrides for a program exercise without mutating other athletes', function () {
    $config = ExercisePlanConfig::from([
        'schedule' => ['weeks' => []],
        'target' => ['measuredReps' => 1, 'measuredWeight' => 50, 'targetGoal' => 10],
    ]);

    $config->setUserExerciseOverrides(10, 100, ExerciseOverrides::from([
        'gridOverrides' => ['sessions' => [], 'cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 8]]]],
    ]));
    $config->setUserExerciseOverrides(11, 100, ExerciseOverrides::from([
        'gridOverrides' => ['sessions' => [], 'cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 10]]]],
    ]));
    $config->setUserExerciseOverrides(11, 101, ExerciseOverrides::from([
        'gridOverrides' => ['sessions' => [], 'cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 12]]]],
    ]));

    $config->removeExerciseOverridesForAllUsers(100);

    expect($config->allUserExerciseOverrides())->toHaveKey(11)
        ->and($config->allUserExerciseOverrides())->not->toHaveKey(10)
        ->and($config->allUserExerciseOverrides()[11])->not->toHaveKey(100)
        ->and($config->allUserExerciseOverrides()[11])->toHaveKey(101);
});

it('remaps athlete-specific overrides when pivot ids change', function () {
    $config = ExercisePlanConfig::from([
        'schedule' => ['weeks' => []],
        'target' => ['measuredReps' => 1, 'measuredWeight' => 50, 'targetGoal' => 10],
    ]);

    $config->setUserExerciseOverrides(10, 100, ExerciseOverrides::from([
        'gridOverrides' => ['sessions' => [], 'cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 8]]]],
    ]));
    $config->setUserExerciseOverrides(11, 101, ExerciseOverrides::from([
        'gridOverrides' => ['sessions' => [], 'cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 10]]]],
    ]));

    $config->remapUserExerciseOverrides([
        100 => 200,
        101 => 201,
    ]);

    expect($config->allUserExerciseOverrides()[10])->toHaveKey(200)
        ->and($config->allUserExerciseOverrides()[10])->not->toHaveKey(100)
        ->and($config->allUserExerciseOverrides()[11])->toHaveKey(201)
        ->and($config->allUserExerciseOverrides()[11])->not->toHaveKey(101);
});

it('copies mapped default and athlete-specific overrides from another config', function () {
    $source = ExercisePlanConfig::from([
        'schedule' => ['weeks' => []],
        'target' => ['measuredReps' => 1, 'measuredWeight' => 50, 'targetGoal' => 10],
    ]);
    $target = ExercisePlanConfig::from([
        'schedule' => ['weeks' => []],
        'target' => ['measuredReps' => 1, 'measuredWeight' => 50, 'targetGoal' => 10],
    ]);

    $source->setDefaultExerciseOverrides(100, ExerciseOverrides::from([
        'startsAtDate' => '2026-01-01',
        'gridOverrides' => ['sessions' => [], 'cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 8]]]],
    ]));
    $source->setUserExerciseOverrides(10, 100, ExerciseOverrides::from([
        'startsAtDate' => '2026-01-02',
        'gridOverrides' => ['sessions' => [], 'cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 10]]]],
    ]));

    $target->copyMappedExerciseOverridesFrom($source, [100 => 200], '2026-02-01');

    expect($target->defaultExerciseOverrides(200)->startsAtDate)->toBe('2026-02-01')
        ->and($target->defaultExerciseOverrides(200)->gridOverrides['cells'][0]['data']['reps'] ?? null)->toBe(8)
        ->and($target->userExerciseOverrides(10, 200)->startsAtDate)->toBe('2026-02-01')
        ->and($target->userExerciseOverrides(10, 200)->gridOverrides['cells'][0]['data']['reps'] ?? null)->toBe(10);
});

it('clears starts-at dates from default and athlete-specific overrides', function () {
    $config = ExercisePlanConfig::from([
        'schedule' => ['weeks' => []],
        'target' => ['measuredReps' => 1, 'measuredWeight' => 50, 'targetGoal' => 10],
    ]);

    $config->setDefaultExerciseOverrides(100, ExerciseOverrides::from([
        'startsAtDate' => '2026-01-01',
    ]));
    $config->setUserExerciseOverrides(10, 100, ExerciseOverrides::from([
        'startsAtDate' => '2026-01-02',
    ]));

    $changed = $config->clearStartsAtDates();

    expect($changed)->toBeTrue()
        ->and($config->defaultExerciseOverrides(100)->startsAtDate)->toBeNull()
        ->and($config->userExerciseOverrides(10, 100)->startsAtDate)->toBeNull();
});

it('exposes override rows as a flat list separate from the persisted json shape', function () {
    $config = ExercisePlanConfig::from([
        'target' => ['measuredReps' => 1, 'measuredWeight' => 50, 'targetGoal' => 10],
    ]);

    $config->setDefaultExerciseOverrides(100, ExerciseOverrides::from([
        'rest' => ['default' => 90, 'applyPer' => 'week'],
        'gridOverrides' => [
            'sessions' => [
                ['week' => 0, 'session' => 0, 'data' => ['rest' => 75]],
            ],
            'cells' => [
                ['week' => 1, 'session' => 0, 'set' => 0, 'data' => ['reps' => 8]],
            ],
        ],
        'historicalGridOverrides' => [
            'sessions' => [],
            'cells' => [
                ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 10]],
            ],
        ],
    ]));

    $persisted = $config->toPersistedArray();
    $rows = $config->flatOverrideRows();

    expect($persisted)->not->toHaveKey('overrideValues')
        ->and($persisted['exercises'][100]['gridOverrides'] ?? null)->toBeNull()
        ->and($rows)->toHaveCount(3);

    $rehydrated = ExercisePlanConfig::from($persisted + ['overrideValues' => $rows]);

    expect($rehydrated->defaultExerciseOverrides(100)->rest?->default)->toBe(90)
        ->and($rehydrated->defaultExerciseOverrides(100)->gridOverrides['sessions'][0]['data']['rest'] ?? null)->toBe(75)
        ->and($rehydrated->defaultExerciseOverrides(100)->gridOverrides['cells'][0]['data']['reps'] ?? null)->toBe(8)
        ->and($rehydrated->defaultExerciseOverrides(100)->historicalGridOverrides['cells'][0]['data']['reps'] ?? null)->toBe(10);
});

it('applies session grouping copy defaults into preview config', function () {
    $config = ExercisePlanConfig::from([
        'sessionGrouping' => [
            'mode' => 'groups',
            'groupSize' => 2,
            'copyValuesAutomatically' => false,
        ],
    ]);

    $resolved = $config->applySessionGroupingToPreview([
        'preview' => [],
    ]);

    expect($resolved['preview'])
        ->toMatchArray([
            'groupingMode' => 'groups',
            'groupSize' => 2,
            'copyValuesAutomatically' => false,
        ]);
});

it('keeps a concrete exercise-level session grouping override when plan grouping changes', function () {
    $config = ExercisePlanConfig::from([
        'sessionGrouping' => [
            'mode' => 'groups',
            'groupSize' => 2,
            'copyValuesAutomatically' => true,
        ],
    ]);

    $config->setDefaultExerciseOverrides(100, ExerciseOverrides::from([
        'sessionGrouping' => [
            'mode' => 'week',
            'groupSize' => 1,
            'copyValuesAutomatically' => false,
        ],
    ]));

    $base = ExerciseConfig::from([
        'settings' => ['reps'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
    ]);

    $resolved = $config->resolveExercise($base, 100);

    expect($resolved->effectiveConfig['preview'] ?? [])
        ->toMatchArray([
            'groupingMode' => 'week',
            'groupSize' => 1,
            'copyValuesAutomatically' => false,
        ]);

    $config->sessionGrouping = \App\Data\Exercise\Preview\SessionGroupingConfig::from([
        'mode' => 'none',
        'groupSize' => 1,
        'copyValuesAutomatically' => false,
    ]);

    $resolvedAfterPlanChange = $config->resolveExercise($base, 100);

    expect($resolvedAfterPlanChange->effectiveConfig['preview'] ?? [])
        ->toMatchArray([
            'groupingMode' => 'week',
            'groupSize' => 1,
            'copyValuesAutomatically' => false,
        ]);
});
