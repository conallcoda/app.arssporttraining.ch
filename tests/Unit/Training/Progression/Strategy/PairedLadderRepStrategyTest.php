<?php

use App\Models\Training\Progression\Config\ResolvedExerciseConfig;
use App\Models\Training\Progression\Strategy\Rep\PairedLadderRepStrategy;

describe('PairedLadderRepStrategy', function () {
    beforeEach(function () {
        $this->strategy = new PairedLadderRepStrategy;
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

    it('calculates reps in pairs', function () {
        $reps = [];
        for ($set = 0; $set < 4; $set++) {
            $reps[] = $this->strategy->calculateSetReps($this->config, 0, $set, 4);
        }

        expect($reps[0])->toBe($reps[1]);
        expect($reps[2])->toBe($reps[3]);
    });

    it('second pair has lower reps than first pair', function () {
        $set0 = $this->strategy->calculateSetReps($this->config, 0, 0, 4);
        $set2 = $this->strategy->calculateSetReps($this->config, 0, 2, 4);

        expect($set2)->toBeLessThan($set0);
    });

    it('respects step-down interval', function () {
        $week0Set0 = $this->strategy->calculateSetReps($this->config, 0, 0, 3);
        $week1Set0 = $this->strategy->calculateSetReps($this->config, 1, 0, 4);
        $week2Set0 = $this->strategy->calculateSetReps($this->config, 2, 0, 3);

        expect($week0Set0)->toBe($week1Set0);
        expect($week2Set0)->toBeLessThan($week0Set0);
    });

    it('respects minimum reps', function () {
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

        $week4Set2 = $this->strategy->calculateSetReps($config, 4, 2, 3);

        expect($week4Set2)->toBeGreaterThanOrEqual(6);
    });

    it('ignores weight and reference 1RM parameters', function () {
        $repsWithWeight = $this->strategy->calculateSetReps($this->config, 0, 0, 3, 50.0, 70.0);
        $repsWithoutWeight = $this->strategy->calculateSetReps($this->config, 0, 0, 3);

        expect($repsWithWeight)->toBe($repsWithoutWeight);
    });

    it('returns derived 1RM for reference 1RM', function () {
        $reference1RM = $this->strategy->getReference1RM($this->config, 70.0, 78.75, 2);

        expect($reference1RM)->toBe(70.0);
    });

    it('generates rep pattern for a week', function () {
        $pattern = $this->strategy->getRepPattern($this->config, 0);

        expect($pattern)->toHaveCount(3);
        expect($pattern[0])->toBe($pattern[1]);
    });

    it('generates full block pattern', function () {
        $pattern = $this->strategy->getFullBlockPattern($this->config);

        expect($pattern)->toHaveCount(5);
        expect($pattern[0])->toHaveCount(3);
        expect($pattern[1])->toHaveCount(4);
    });

    it('matches example from spec for week 1', function () {
        $pattern = $this->strategy->getRepPattern($this->config, 0);

        expect($pattern[0])->toBe(14);
        expect($pattern[1])->toBe(14);
        expect($pattern[2])->toBe(12);
    });

    it('matches example from spec for week 5', function () {
        $pattern = $this->strategy->getRepPattern($this->config, 4);

        expect($pattern[0])->toBe(10);
        expect($pattern[1])->toBe(10);
        expect($pattern[2])->toBe(8);
    });
});
