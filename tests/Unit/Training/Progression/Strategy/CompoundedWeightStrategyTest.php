<?php

use App\Models\Training\Progression\Strategy\Weight\CompoundedWeightStrategy;
use Tests\Unit\Training\Progression\ProgressionTestHelpers;

uses(ProgressionTestHelpers::class);

describe('CompoundedWeightStrategy', function () {
    beforeEach(function () {
        $this->strategy = new CompoundedWeightStrategy;
        $this->config = $this->createCompoundedResolvedConfig(exerciseId: 1, startingReps: 12);
        $this->derived1RM = 70.0;
    });

    it('calculates target 1RM correctly', function () {
        $target1RM = $this->strategy->getTarget1RM($this->config, $this->derived1RM);

        expect($target1RM)->toBe(78.75);
    });

    it('calculates weekly growth rate', function () {
        $growthRate = $this->strategy->calculateWeeklyGrowthRate($this->config);

        expect($growthRate)->toBeGreaterThan(0.02);
        expect($growthRate)->toBeLessThan(0.03);
    });

    it('calculates projected 1RM increasing each week', function () {
        $projected1RMs = [];
        for ($week = 0; $week < 5; $week++) {
            $projected1RMs[$week] = $this->strategy->getProjected1RM($this->config, $this->derived1RM, $week);
        }

        expect($projected1RMs[0])->toBe($this->derived1RM);
        expect($projected1RMs[1])->toBeGreaterThan($projected1RMs[0]);
        expect($projected1RMs[2])->toBeGreaterThan($projected1RMs[1]);
        expect($projected1RMs[3])->toBeGreaterThan($projected1RMs[2]);
        expect($projected1RMs[4])->toBeGreaterThan($projected1RMs[3]);
    });

    it('week 1 projected 1RM equals derived 1RM', function () {
        $projected1RM = $this->strategy->getProjected1RM($this->config, $this->derived1RM, 0);

        expect($projected1RM)->toBe(70.0);
    });

    it('final week projected 1RM equals target 1RM', function () {
        $projected1RM = $this->strategy->getProjected1RM($this->config, $this->derived1RM, 4);
        $target1RM = $this->strategy->getTarget1RM($this->config, $this->derived1RM);

        expect($projected1RM)->toBeGreaterThan($target1RM - 0.1)->toBeLessThan($target1RM + 0.1);
    });

    it('calculates anchor weights based on projected 1RM', function () {
        $anchors = [];
        for ($week = 0; $week < 5; $week++) {
            $anchors[$week] = $this->strategy->calculateAnchorWeight($this->config, $this->derived1RM, $week);
        }

        expect($anchors[0])->toBeLessThan($anchors[4]);
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

    it('can recalculate from override', function () {
        $overrideAnchor = 55.0;
        $overrideWeek = 2;
        $targetWeek = 4;

        $projected = $this->strategy->recalculateFromOverride(
            $this->config,
            $overrideAnchor,
            $overrideWeek,
            $targetWeek,
        );

        expect($projected)->toBeGreaterThan($overrideAnchor / 0.80);
    });

    it('matches example from spec for week 1 anchor', function () {
        $anchorWeight = $this->strategy->calculateAnchorWeight($this->config, $this->derived1RM, 0);

        expect($anchorWeight)->toBeGreaterThan(51.0)->toBeLessThan(53.0);
    });

    it('matches example from spec for week 5 anchor', function () {
        $anchorWeight = $this->strategy->calculateAnchorWeight($this->config, $this->derived1RM, 4);

        expect($anchorWeight)->toBeGreaterThan(66.5)->toBeLessThan(68.5);
    });
});
