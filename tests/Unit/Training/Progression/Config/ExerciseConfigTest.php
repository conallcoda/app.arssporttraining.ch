<?php

use App\Models\Training\Progression\Config\ExerciseConfig;
use App\Models\Training\Progression\Strategy\Weight\CompoundedWeightConfig;

describe('ExerciseConfig', function () {
    it('can be created with only exercise id', function () {
        $config = new ExerciseConfig(exerciseId: 1);

        expect($config->exerciseId)->toBe(1);
        expect($config->weightConfig)->toBeNull();
        expect($config->repConfig)->toBeNull();
        expect($config->modifier)->toBe(1.0);
    });

    it('can be created with overrides', function () {
        $weightConfig = new CompoundedWeightConfig(targetImprovement: 15);
        $config = new ExerciseConfig(
            exerciseId: 1,
            weightConfig: $weightConfig,
            modifier: 0.85,
        );

        expect($config->exerciseId)->toBe(1);
        expect($config->weightConfig)->toBe($weightConfig);
        expect($config->weightConfig->targetImprovement)->toBe(15.0);
        expect($config->modifier)->toBe(0.85);
    });

    it('detects if a specific override exists', function () {
        $config = new ExerciseConfig(
            exerciseId: 1,
            weightConfig: new CompoundedWeightConfig,
        );

        expect($config->hasOverride('weightConfig'))->toBeTrue();
        expect($config->hasOverride('repConfig'))->toBeFalse();
    });

    it('returns all non-null overrides', function () {
        $config = new ExerciseConfig(
            exerciseId: 1,
            weightConfig: new CompoundedWeightConfig(targetImprovement: 15),
            modifier: 0.85,
        );

        $overrides = $config->getOverrides();

        expect($overrides)->toHaveKey('weightConfig');
        expect($overrides)->toHaveKey('modifier');
        expect($overrides)->not->toHaveKey('repConfig');
    });

    it('does not include default modifier in overrides', function () {
        $config = new ExerciseConfig(
            exerciseId: 1,
            weightConfig: new CompoundedWeightConfig,
            modifier: 1.0,
        );

        $overrides = $config->getOverrides();

        expect($overrides)->not->toHaveKey('modifier');
    });
});
