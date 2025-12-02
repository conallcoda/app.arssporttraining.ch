<?php

use App\Models\Training\Progression\Config\ExerciseConfig;
use App\Models\Training\Progression\Config\ProgressionConfig;
use App\Models\Training\Progression\Config\ResolvedExerciseConfig;
use App\Models\Training\Progression\Strategy\Rep\PairedLadderRepConfig;
use App\Models\Training\Progression\Strategy\Weight\CompoundedWeightConfig;
use App\Models\Training\Progression\Strategy\Weight\FixedStepWeightConfig;
use Tests\Unit\Training\Progression\ProgressionTestHelpers;

uses(ProgressionTestHelpers::class);

describe('ProgressionConfig', function () {
    it('has sensible defaults', function () {
        $config = new ProgressionConfig;

        expect($config->blockLength)->toBe(5);
        expect($config->weightConfig)->toBeInstanceOf(FixedStepWeightConfig::class);
        expect($config->repConfig)->toBeInstanceOf(PairedLadderRepConfig::class);
        expect($config->weightConfig->targetImprovement)->toBe(12.5);
        expect($config->weightConfig->incrementStep)->toBe(0.5);
        expect($config->repConfig->startingReps)->toBe(14);
        expect($config->repConfig->stepDownInterval)->toBe(2);
        expect($config->repConfig->repDecrement)->toBe(2);
        expect($config->repConfig->minimumReps)->toBe(6);
        expect($config->exerciseOverrides)->toBe([]);
    });

    it('resolves config for exercise without overrides', function () {
        $config = $this->createDefaultConfig();
        $resolved = $config->forExercise(1);

        expect($resolved)->toBeInstanceOf(ResolvedExerciseConfig::class);
        expect($resolved->exerciseId)->toBe(1);
        expect($resolved->weightConfig)->toBeInstanceOf(FixedStepWeightConfig::class);
        expect($resolved->repConfig)->toBeInstanceOf(PairedLadderRepConfig::class);
        expect($resolved->weightConfig->targetImprovement)->toBe(12.5);
        expect($resolved->modifier)->toBe(1.0);
    });

    it('resolves config for exercise with overrides', function () {
        $config = $this->createDefaultConfig();
        $config->exerciseOverrides[1] = new ExerciseConfig(
            exerciseId: 1,
            weightConfig: new CompoundedWeightConfig(targetImprovement: 15),
            modifier: 0.85,
        );

        $resolved = $config->forExercise(1);

        expect($resolved->weightConfig)->toBeInstanceOf(CompoundedWeightConfig::class);
        expect($resolved->weightConfig->targetImprovement)->toBe(15.0);
        expect($resolved->modifier)->toBe(0.85);
        expect($resolved->repConfig)->toBeInstanceOf(PairedLadderRepConfig::class);
    });

    it('can set exercise override', function () {
        $config = $this->createDefaultConfig();
        $config->setExerciseOverride(1, 'weightConfig', new CompoundedWeightConfig);
        $config->setExerciseOverride(1, 'modifier', 0.85);

        expect($config->hasExerciseOverride(1))->toBeTrue();
        expect($config->hasExerciseOverride(1, 'weightConfig'))->toBeTrue();
        expect($config->hasExerciseOverride(1, 'modifier'))->toBeTrue();
        expect($config->hasExerciseOverride(1, 'repConfig'))->toBeFalse();
    });

    it('can clear specific exercise override', function () {
        $config = $this->createDefaultConfig();
        $config->setExerciseOverride(1, 'weightConfig', new CompoundedWeightConfig);
        $config->setExerciseOverride(1, 'modifier', 0.85);

        $config->clearExerciseOverride(1, 'weightConfig');

        expect($config->hasExerciseOverride(1, 'weightConfig'))->toBeFalse();
        expect($config->hasExerciseOverride(1, 'modifier'))->toBeTrue();
    });

    it('can clear all exercise overrides', function () {
        $config = $this->createDefaultConfig();
        $config->setExerciseOverride(1, 'weightConfig', new CompoundedWeightConfig);
        $config->setExerciseOverride(1, 'modifier', 0.85);

        $config->clearExerciseOverride(1);

        expect($config->hasExerciseOverride(1))->toBeFalse();
    });

    it('returns null for non-existent exercise override', function () {
        $config = $this->createDefaultConfig();

        expect($config->getExerciseOverride(999))->toBeNull();
    });

    it('detects exercise override existence', function () {
        $config = $this->createDefaultConfig();

        expect($config->hasExerciseOverride(1))->toBeFalse();

        $config->setExerciseOverride(1, 'modifier', 0.85);

        expect($config->hasExerciseOverride(1))->toBeTrue();
        expect($config->hasExerciseOverride(2))->toBeFalse();
    });
});
