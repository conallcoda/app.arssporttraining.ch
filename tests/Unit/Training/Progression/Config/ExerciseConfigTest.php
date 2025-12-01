<?php

use App\Models\Training\Progression\Config\ExerciseConfig;

describe('ExerciseConfig', function () {
    it('can be created with only exercise id', function () {
        $config = new ExerciseConfig(exerciseId: 1);

        expect($config->exerciseId)->toBe(1);
        expect($config->weightStrategy)->toBeNull();
        expect($config->repStrategy)->toBeNull();
        expect($config->modifier)->toBe(1.0);
    });

    it('can be created with overrides', function () {
        $config = new ExerciseConfig(
            exerciseId: 1,
            weightStrategy: 'compounded',
            targetImprovement: 0.15,
            modifier: 0.85,
        );

        expect($config->exerciseId)->toBe(1);
        expect($config->weightStrategy)->toBe('compounded');
        expect($config->targetImprovement)->toBe(0.15);
        expect($config->modifier)->toBe(0.85);
    });

    it('detects if a specific override exists', function () {
        $config = new ExerciseConfig(
            exerciseId: 1,
            weightStrategy: 'compounded',
        );

        expect($config->hasOverride('weightStrategy'))->toBeTrue();
        expect($config->hasOverride('repStrategy'))->toBeFalse();
        expect($config->hasOverride('targetImprovement'))->toBeFalse();
    });

    it('returns all non-null overrides', function () {
        $config = new ExerciseConfig(
            exerciseId: 1,
            weightStrategy: 'compounded',
            targetImprovement: 0.15,
            modifier: 0.85,
        );

        $overrides = $config->getOverrides();

        expect($overrides)->toHaveKey('weightStrategy');
        expect($overrides)->toHaveKey('targetImprovement');
        expect($overrides)->toHaveKey('modifier');
        expect($overrides)->not->toHaveKey('repStrategy');
        expect($overrides)->not->toHaveKey('startingReps');
    });

    it('does not include default modifier in overrides', function () {
        $config = new ExerciseConfig(
            exerciseId: 1,
            weightStrategy: 'compounded',
            modifier: 1.0,
        );

        $overrides = $config->getOverrides();

        expect($overrides)->not->toHaveKey('modifier');
    });
});
