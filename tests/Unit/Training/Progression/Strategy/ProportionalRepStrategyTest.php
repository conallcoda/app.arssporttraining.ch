<?php

use App\Models\Training\Progression\Config\ResolvedExerciseConfig;
use App\Models\Training\Progression\Strategy\Rep\ProportionalRepStrategy;

describe('ProportionalRepStrategy', function () {
    beforeEach(function () {
        $this->strategy = new ProportionalRepStrategy;
        $this->config = new ResolvedExerciseConfig(
            exerciseId: 1,
            weightStrategy: 'fixed_step',
            repStrategy: 'proportional',
            targetImprovement: 0.125,
            startingReps: 14,
            stepDownInterval: 2,
            repDecrement: 2,
            minimumReps: 6,
            incrementStep: 0.5,
            blockLength: 5,
            modifier: 1.0,
        );
        $this->derived1RM = 70.0;
        $this->target1RM = 78.75;
    });

    it('returns anchor reps for anchor set', function () {
        $totalSets = 3;
        $anchorIndex = 2;

        $anchorReps = $this->strategy->calculateSetReps(
            $this->config,
            0,
            $anchorIndex,
            $totalSets,
            52.0,
            70.0,
        );

        expect($anchorReps)->toBe($this->config->getAnchorRepsForWeek(0));
    });

    it('calculates reps based on weight percentage for non-anchor sets', function () {
        $reference1RM = 70.0;
        $setWeight = 47.0;
        $percentage = $setWeight / $reference1RM;

        $reps = $this->strategy->calculateSetReps(
            $this->config,
            0,
            0,
            3,
            $setWeight,
            $reference1RM,
        );

        expect($reps)->toBeGreaterThanOrEqual(10);
        expect($reps)->toBeLessThanOrEqual(14);
    });

    it('returns anchor reps when weight is null', function () {
        $reps = $this->strategy->calculateSetReps(
            $this->config,
            0,
            0,
            3,
            null,
            70.0,
        );

        expect($reps)->toBe($this->config->getAnchorRepsForWeek(0));
    });

    it('returns anchor reps when reference 1RM is null', function () {
        $reps = $this->strategy->calculateSetReps(
            $this->config,
            0,
            0,
            3,
            50.0,
            null,
        );

        expect($reps)->toBe($this->config->getAnchorRepsForWeek(0));
    });

    it('respects minimum reps', function () {
        $reps = $this->strategy->calculateSetReps(
            $this->config,
            4,
            0,
            3,
            65.0,
            70.0,
        );

        expect($reps)->toBeGreaterThanOrEqual(6);
    });

    it('calculates reference 1RM using linear interpolation', function () {
        $ref0 = $this->strategy->getReference1RM($this->config, $this->derived1RM, $this->target1RM, 0);
        $ref2 = $this->strategy->getReference1RM($this->config, $this->derived1RM, $this->target1RM, 2);
        $ref4 = $this->strategy->getReference1RM($this->config, $this->derived1RM, $this->target1RM, 4);

        expect($ref0)->toBe($this->derived1RM);
        expect($ref4)->toBe($this->target1RM);
        expect($ref2)->toBeGreaterThan($ref0);
        expect($ref2)->toBeLessThan($ref4);
    });

    it('week 1 reference 1RM equals derived 1RM', function () {
        $ref = $this->strategy->getReference1RM($this->config, $this->derived1RM, $this->target1RM, 0);

        expect($ref)->toBe(70.0);
    });

    it('final week reference 1RM equals target 1RM', function () {
        $ref = $this->strategy->getReference1RM($this->config, $this->derived1RM, $this->target1RM, 4);

        expect($ref)->toBe(78.75);
    });

    it('calculates reps from percentage correctly', function () {
        expect($this->strategy->calculateRepsFromPercentage(0.85, 6))->toBe(6);
        expect($this->strategy->calculateRepsFromPercentage(0.78, 6))->toBe(8);
        expect($this->strategy->calculateRepsFromPercentage(0.72, 6))->toBe(10);
        expect($this->strategy->calculateRepsFromPercentage(0.66, 6))->toBe(12);
        expect($this->strategy->calculateRepsFromPercentage(0.62, 6))->toBe(14);
    });

    it('lighter sets get more reps than heavier sets', function () {
        $reference1RM = 70.0;

        $lightSetReps = $this->strategy->calculateSetReps($this->config, 0, 0, 3, 42.5, $reference1RM);
        $heavySetReps = $this->strategy->calculateSetReps($this->config, 0, 1, 3, 52.0, $reference1RM);

        expect($lightSetReps)->toBeGreaterThanOrEqual($heavySetReps);
    });
});
