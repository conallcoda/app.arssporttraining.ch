<?php

use App\Models\Training\Progression\Config\ResolvedExerciseConfig;
use App\Models\Training\Progression\Strategy\Rep\FixedRepConfig;
use App\Models\Training\Progression\Strategy\Rep\FixedRepStrategy;
use App\Models\Training\Progression\Strategy\Weight\FixedStepWeightConfig;

describe('FixedRepStrategy', function () {
    beforeEach(function () {
        $this->strategy = new FixedRepStrategy;
        $this->config = new ResolvedExerciseConfig(
            exerciseId: 1,
            blockLength: 5,
            weightConfig: new FixedStepWeightConfig(
                targetImprovement: 12.5,
                incrementStep: 0.5,
            ),
            repConfig: new FixedRepConfig(
                reps: 8,
            ),
            modifier: 1.0,
        );
    });

    it('returns same reps for all sets', function () {
        $reps = [];
        for ($set = 0; $set < 4; $set++) {
            $reps[] = $this->strategy->calculateSetReps($this->config, 0, $set, 4);
        }

        expect($reps)->each->toBe(8);
    });

    it('returns same reps across all weeks', function () {
        $reps = [];
        for ($week = 0; $week < 5; $week++) {
            $reps[] = $this->strategy->calculateSetReps($this->config, $week, 0, 3);
        }

        expect($reps)->each->toBe(8);
    });

    it('uses configured rep value', function () {
        $config = new ResolvedExerciseConfig(
            exerciseId: 1,
            blockLength: 5,
            weightConfig: new FixedStepWeightConfig(
                targetImprovement: 12.5,
                incrementStep: 0.5,
            ),
            repConfig: new FixedRepConfig(
                reps: 12,
            ),
            modifier: 1.0,
        );

        $reps = $this->strategy->calculateSetReps($config, 0, 0, 3);

        expect($reps)->toBe(12);
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

    it('generates rep pattern with all same values', function () {
        $pattern = $this->strategy->getRepPattern($this->config, 0);

        expect($pattern)->toHaveCount(3);
        expect($pattern)->each->toBe(8);
    });

    it('generates full block pattern', function () {
        $pattern = $this->strategy->getFullBlockPattern($this->config);

        expect($pattern)->toHaveCount(5);
        foreach ($pattern as $weekPattern) {
            expect($weekPattern)->each->toBe(8);
        }
    });
});
