<?php

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
        'gridOverrides' => ['cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 8]]], 'weeks' => []],
    ]));
    $config->setUserExerciseOverrides(11, 100, ExerciseOverrides::from([
        'gridOverrides' => ['cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 10]]], 'weeks' => []],
    ]));
    $config->setUserExerciseOverrides(11, 101, ExerciseOverrides::from([
        'gridOverrides' => ['cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 12]]], 'weeks' => []],
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
        'gridOverrides' => ['cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 8]]], 'weeks' => []],
    ]));
    $config->setUserExerciseOverrides(11, 101, ExerciseOverrides::from([
        'gridOverrides' => ['cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 10]]], 'weeks' => []],
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
        'gridOverrides' => ['cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 8]]], 'weeks' => []],
    ]));
    $source->setUserExerciseOverrides(10, 100, ExerciseOverrides::from([
        'startsAtDate' => '2026-01-02',
        'gridOverrides' => ['cells' => [['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 10]]], 'weeks' => []],
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
