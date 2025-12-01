<?php

use App\Models\Training\Progression\Config\ResolvedExerciseConfig;
use App\Models\Training\Progression\Strategy\Weight\FixedStepWeightStrategy;

describe('FixedStepWeightStrategy', function () {
    beforeEach(function () {
        $this->strategy = new FixedStepWeightStrategy;
        $this->config = new ResolvedExerciseConfig(
            exerciseId: 1,
            weightStrategy: 'fixed_step',
            repStrategy: 'paired_ladder',
            targetImprovement: 0.125,
            startingReps: 12,
            stepDownInterval: 2,
            repDecrement: 2,
            minimumReps: 6,
            incrementStep: 0.5,
            blockLength: 5,
            modifier: 1.0,
        );
        $this->derived1RM = 70.0;
    });

    it('calculates target 1RM correctly', function () {
        $target1RM = $this->strategy->getTarget1RM($this->config, $this->derived1RM);

        expect($target1RM)->toBe(78.75);
    });

    it('calculates final week anchor weight', function () {
        $anchorWeight = $this->strategy->calculateAnchorWeight($this->config, $this->derived1RM, 4);

        expect($anchorWeight)->toBeGreaterThan(60.0);
        expect($anchorWeight)->toBeLessThan(70.0);
    });

    it('calculates anchor weights decreasing backwards from final', function () {
        $anchors = [];
        for ($week = 0; $week < 5; $week++) {
            $anchors[$week] = $this->strategy->calculateAnchorWeight($this->config, $this->derived1RM, $week);
        }

        expect($anchors[0])->toBeLessThan($anchors[1]);
        expect($anchors[1])->toBeLessThan($anchors[2]);
        expect($anchors[2])->toBeLessThan($anchors[3]);
        expect($anchors[3])->toBeLessThan($anchors[4]);
    });

    it('calculates set weights decreasing from anchor', function () {
        $anchorWeight = 67.5;
        $totalSets = 3;

        $set0 = $this->strategy->calculateSetWeight($this->config, $anchorWeight, 0, $totalSets);
        $set1 = $this->strategy->calculateSetWeight($this->config, $anchorWeight, 1, $totalSets);
        $set2 = $this->strategy->calculateSetWeight($this->config, $anchorWeight, 2, $totalSets);

        expect($set0)->toBeLessThan($set1);
        expect($set1)->toBeLessThan($set2);
        expect($set2)->toBe($anchorWeight);
    });

    it('uses correct weight brackets for set decrements', function () {
        $anchorWeight = 67.5;
        $totalSets = 3;

        $set2 = $this->strategy->calculateSetWeight($this->config, $anchorWeight, 2, $totalSets);
        $set1 = $this->strategy->calculateSetWeight($this->config, $anchorWeight, 1, $totalSets);
        $set0 = $this->strategy->calculateSetWeight($this->config, $anchorWeight, 0, $totalSets);

        expect($set2 - $set1)->toBe(5.0);
        expect($set1 - $set0)->toBe(5.0);
    });

    it('calculates projected 1RM for each week', function () {
        $projected1RMs = [];
        for ($week = 0; $week < 5; $week++) {
            $projected1RMs[$week] = $this->strategy->getProjected1RM($this->config, $this->derived1RM, $week);
        }

        expect($projected1RMs[0])->toBeLessThan($projected1RMs[4]);
    });

    it('matches example from spec for week 5', function () {
        $anchorWeight = $this->strategy->calculateAnchorWeight($this->config, $this->derived1RM, 4);

        expect($anchorWeight)->toBeGreaterThan(67.0)->toBeLessThan(68.0);
    });
});
