<?php

use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Settings\WeightSetting;
use App\Data\Training\Config\ExerciseOverrides;
use App\Data\Training\Config\TrainingPlanConfig;

it('returns empty ExerciseOverrides for unknown exercise id', function () {
    $config = TrainingPlanConfig::initialize();

    $overrides = $config->defaultExerciseOverrides(999);

    expect($overrides)->toBeInstanceOf(ExerciseOverrides::class);
    expect($overrides->settings)->toBeNull();
    expect($overrides->reps)->toBeNull();
});

it('sets and gets default exercise overrides', function () {
    $config = TrainingPlanConfig::initialize();

    $overrides = new ExerciseOverrides(
        reps: new RepsSetting(default: 10),
        weight: new WeightSetting(oneRepMaxModifier: 85),
    );

    $config->setDefaultExerciseOverrides(42, $overrides);

    $retrieved = $config->defaultExerciseOverrides(42);
    expect($retrieved->reps->default)->toBe(10);
    expect($retrieved->weight->oneRepMaxModifier)->toBe(85);
});

it('returns empty ExerciseOverrides for unknown user exercise', function () {
    $config = TrainingPlanConfig::initialize();

    $overrides = $config->userExerciseOverrides(42, 1);

    expect($overrides)->toBeInstanceOf(ExerciseOverrides::class);
    expect($overrides->reps)->toBeNull();
});

it('sets and gets user exercise overrides', function () {
    $config = TrainingPlanConfig::initialize();

    $overrides = new ExerciseOverrides(
        reps: new RepsSetting(default: 8),
    );

    $config->setUserExerciseOverrides(42, 1, $overrides);

    $retrieved = $config->userExerciseOverrides(42, 1);
    expect($retrieved->reps->default)->toBe(8);
});

it('creates user config when setting user exercise overrides for new user', function () {
    $config = TrainingPlanConfig::initialize();

    expect($config->forUser(99))->toBeNull();

    $config->setUserExerciseOverrides(42, 99, new ExerciseOverrides(
        reps: new RepsSetting(default: 6),
    ));

    expect($config->forUser(99))->not->toBeNull();
    expect($config->userExerciseOverrides(42, 99)->reps->default)->toBe(6);
});

it('removes exercise overrides from default and all users', function () {
    $config = TrainingPlanConfig::initialize();

    $config->setDefaultExerciseOverrides(42, new ExerciseOverrides(
        reps: new RepsSetting(default: 10),
    ));
    $config->setUserExerciseOverrides(42, 1, new ExerciseOverrides(
        reps: new RepsSetting(default: 8),
    ));
    $config->setUserExerciseOverrides(42, 2, new ExerciseOverrides(
        reps: new RepsSetting(default: 6),
    ));

    $config->removeExerciseOverrides(42);

    expect($config->defaultExerciseOverrides(42)->reps)->toBeNull();
    expect($config->userExerciseOverrides(42, 1)->reps)->toBeNull();
    expect($config->userExerciseOverrides(42, 2)->reps)->toBeNull();
});

it('preserves exercise overrides through serialization roundtrip', function () {
    $config = TrainingPlanConfig::initialize();

    $config->setDefaultExerciseOverrides(42, new ExerciseOverrides(
        reps: new RepsSetting(default: 10),
        weight: new WeightSetting(oneRepMaxModifier: 85),
    ));
    $config->setUserExerciseOverrides(42, 1, new ExerciseOverrides(
        reps: new RepsSetting(default: 8),
    ));

    $serialized = json_encode($config->toArray());
    $restored = TrainingPlanConfig::from(json_decode($serialized, true));

    expect($restored->defaultExerciseOverrides(42)->reps->default)->toBe(10);
    expect($restored->defaultExerciseOverrides(42)->weight->oneRepMaxModifier)->toBe(85);
    expect($restored->userExerciseOverrides(42, 1)->reps->default)->toBe(8);
});

it('resets defaults clears exercises', function () {
    $config = TrainingPlanConfig::initialize();

    $config->setDefaultExerciseOverrides(42, new ExerciseOverrides(
        reps: new RepsSetting(default: 10),
    ));

    $config->resetDefaults();

    expect($config->defaultExerciseOverrides(42)->reps)->toBeNull();
});

it('resets all clears exercises from default and users', function () {
    $config = TrainingPlanConfig::initialize();

    $config->setDefaultExerciseOverrides(42, new ExerciseOverrides(
        reps: new RepsSetting(default: 10),
    ));
    $config->setUserExerciseOverrides(42, 1, new ExerciseOverrides(
        reps: new RepsSetting(default: 8),
    ));

    $config->resetAll();

    expect($config->defaultExerciseOverrides(42)->reps)->toBeNull();
    expect($config->userExerciseOverrides(42, 1)->reps)->toBeNull();
});

it('reset defaults preserves schedule and target', function () {
    $config = TrainingPlanConfig::initialize();

    $config->setDefaultExerciseOverrides(42, new ExerciseOverrides(
        reps: new RepsSetting(default: 10),
    ));

    $scheduleBefore = $config->defaultScheduleWeeks();
    $targetBefore = $config->defaultTargetGoal();

    $config->resetDefaults();

    expect($config->defaultScheduleWeeks())->toBe($scheduleBefore);
    expect($config->defaultTargetGoal())->toBe($targetBefore);
    expect($config->defaultExerciseOverrides(42)->reps)->toBeNull();
});

it('reset all preserves schedule and target', function () {
    $config = TrainingPlanConfig::initialize();

    $config->setDefaultExerciseOverrides(42, new ExerciseOverrides(
        reps: new RepsSetting(default: 10),
    ));
    $config->setUserExerciseOverrides(42, 1, new ExerciseOverrides(
        reps: new RepsSetting(default: 8),
    ));

    $scheduleBefore = $config->defaultScheduleWeeks();

    $config->resetAll();

    expect($config->defaultScheduleWeeks())->toBe($scheduleBefore);
    expect($config->defaultExerciseOverrides(42)->reps)->toBeNull();
    expect($config->userExerciseOverrides(42, 1)->reps)->toBeNull();
});

it('hasDefaultOverrides returns true when exercises are set', function () {
    $config = TrainingPlanConfig::initialize();

    expect($config->hasDefaultOverrides())->toBeFalse();

    $config->setDefaultExerciseOverrides(42, new ExerciseOverrides(
        reps: new RepsSetting(default: 10),
    ));

    expect($config->hasDefaultOverrides())->toBeTrue();
});

it('does not affect other exercises when removing one', function () {
    $config = TrainingPlanConfig::initialize();

    $config->setDefaultExerciseOverrides(42, new ExerciseOverrides(
        reps: new RepsSetting(default: 10),
    ));
    $config->setDefaultExerciseOverrides(99, new ExerciseOverrides(
        reps: new RepsSetting(default: 12),
    ));

    $config->removeExerciseOverrides(42);

    expect($config->defaultExerciseOverrides(42)->reps)->toBeNull();
    expect($config->defaultExerciseOverrides(99)->reps->default)->toBe(12);
});
