<?php

use App\Models\Training\Progression\Config\ExerciseConfig;
use App\Models\Training\Progression\Config\ProgressionConfig;
use App\Models\Training\Progression\Config\ResolvedExerciseConfig;
use Tests\Unit\Training\Progression\ProgressionTestHelpers;

uses(ProgressionTestHelpers::class);

describe('ProgressionConfig', function () {
    it('has sensible defaults', function () {
        $config = new ProgressionConfig;

        expect($config->weightStrategy)->toBe('fixed_step');
        expect($config->repStrategy)->toBe('paired_ladder');
        expect($config->targetImprovement)->toBe(0.125);
        expect($config->startingReps)->toBe(14);
        expect($config->stepDownInterval)->toBe(2);
        expect($config->repDecrement)->toBe(2);
        expect($config->minimumReps)->toBe(6);
        expect($config->incrementStep)->toBe(0.5);
        expect($config->blockLength)->toBe(5);
        expect($config->exerciseOverrides)->toBe([]);
    });

    it('resolves config for exercise without overrides', function () {
        $config = $this->createDefaultConfig();
        $resolved = $config->forExercise(1);

        expect($resolved)->toBeInstanceOf(ResolvedExerciseConfig::class);
        expect($resolved->exerciseId)->toBe(1);
        expect($resolved->weightStrategy)->toBe('fixed_step');
        expect($resolved->repStrategy)->toBe('paired_ladder');
        expect($resolved->targetImprovement)->toBe(0.125);
        expect($resolved->modifier)->toBe(1.0);
    });

    it('resolves config for exercise with overrides', function () {
        $config = $this->createDefaultConfig();
        $config->exerciseOverrides[1] = new ExerciseConfig(
            exerciseId: 1,
            weightStrategy: 'compounded',
            targetImprovement: 0.15,
            modifier: 0.85,
        );

        $resolved = $config->forExercise(1);

        expect($resolved->weightStrategy)->toBe('compounded');
        expect($resolved->targetImprovement)->toBe(0.15);
        expect($resolved->modifier)->toBe(0.85);
        expect($resolved->repStrategy)->toBe('paired_ladder');
    });

    it('can set exercise override', function () {
        $config = $this->createDefaultConfig();
        $config->setExerciseOverride(1, 'weightStrategy', 'compounded');
        $config->setExerciseOverride(1, 'modifier', 0.85);

        expect($config->hasExerciseOverride(1))->toBeTrue();
        expect($config->hasExerciseOverride(1, 'weightStrategy'))->toBeTrue();
        expect($config->hasExerciseOverride(1, 'modifier'))->toBeTrue();
        expect($config->hasExerciseOverride(1, 'repStrategy'))->toBeFalse();
    });

    it('can clear specific exercise override', function () {
        $config = $this->createDefaultConfig();
        $config->setExerciseOverride(1, 'weightStrategy', 'compounded');
        $config->setExerciseOverride(1, 'modifier', 0.85);

        $config->clearExerciseOverride(1, 'weightStrategy');

        expect($config->hasExerciseOverride(1, 'weightStrategy'))->toBeFalse();
        expect($config->hasExerciseOverride(1, 'modifier'))->toBeTrue();
    });

    it('can clear all exercise overrides', function () {
        $config = $this->createDefaultConfig();
        $config->setExerciseOverride(1, 'weightStrategy', 'compounded');
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
