<?php

use App\Models\Training\Progression\Config\ResolvedExerciseConfig;

describe('ResolvedExerciseConfig', function () {
    beforeEach(function () {
        $this->config = new ResolvedExerciseConfig(
            exerciseId: 1,
            weightStrategy: 'fixed_step',
            repStrategy: 'paired_ladder',
            targetImprovement: 0.125,
            startingReps: 14,
            stepDownInterval: 2,
            repDecrement: 2,
            minimumReps: 6,
            incrementStep: 0.5,
            blockLength: 5,
            modifier: 1.0,
        );
    });

    it('calculates anchor reps for each week', function () {
        expect($this->config->getAnchorRepsForWeek(0))->toBe(12);
        expect($this->config->getAnchorRepsForWeek(1))->toBe(12);
        expect($this->config->getAnchorRepsForWeek(2))->toBe(10);
        expect($this->config->getAnchorRepsForWeek(3))->toBe(10);
        expect($this->config->getAnchorRepsForWeek(4))->toBe(8);
    });

    it('respects minimum reps floor', function () {
        $config = new ResolvedExerciseConfig(
            exerciseId: 1,
            weightStrategy: 'fixed_step',
            repStrategy: 'paired_ladder',
            targetImprovement: 0.125,
            startingReps: 10,
            stepDownInterval: 1,
            repDecrement: 2,
            minimumReps: 6,
            incrementStep: 0.5,
            blockLength: 5,
            modifier: 1.0,
        );

        expect($config->getAnchorRepsForWeek(0))->toBe(8);
        expect($config->getAnchorRepsForWeek(1))->toBe(6);
        expect($config->getAnchorRepsForWeek(2))->toBe(6);
        expect($config->getAnchorRepsForWeek(3))->toBe(6);
        expect($config->getAnchorRepsForWeek(4))->toBe(6);
    });

    it('returns 3 sets for odd weeks and 4 sets for even weeks', function () {
        expect($this->config->getSetCountForWeek(0))->toBe(3);
        expect($this->config->getSetCountForWeek(1))->toBe(4);
        expect($this->config->getSetCountForWeek(2))->toBe(3);
        expect($this->config->getSetCountForWeek(3))->toBe(4);
        expect($this->config->getSetCountForWeek(4))->toBe(3);
    });

    it('returns rep percentage from table', function () {
        expect($this->config->getRepPercentage(6))->toBe(0.86);
        expect($this->config->getRepPercentage(8))->toBe(0.80);
        expect($this->config->getRepPercentage(10))->toBe(0.74);
        expect($this->config->getRepPercentage(12))->toBe(0.67);
    });

    it('calculates target 1RM', function () {
        $derived1RM = 70.0;
        $target1RM = $this->config->getTarget1RM($derived1RM);

        expect($target1RM)->toBe(78.75);
    });
});
