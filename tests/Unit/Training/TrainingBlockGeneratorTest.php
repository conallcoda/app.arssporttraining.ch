<?php

use App\Training\Data\TrainingBlock;
use App\Training\Services\TrainingBlockGenerator;

it('generates a training block with correct structure', function () {
    $generator = new TrainingBlockGenerator;

    $block = $generator->generate(
        measuredWeight: 52,
        measuredReps: 10,
        oneRepMaxModifier: 100,
        targetPercentage: 7,
        startingReps: 10,
        sets: 4,
        weeks: 5,
        sessionsPerWeek: 2,
        deloadEnabled: true,
        deloadSetsReduction: 1,
    );

    expect($block)->toBeInstanceOf(TrainingBlock::class);
    expect($block->weeks)->toHaveCount(5);
    expect($block->weeks[0]->sessions)->toHaveCount(2);
});

it('calculates correct 1RM from measured data', function () {
    $generator = new TrainingBlockGenerator;

    $block = $generator->generate(
        measuredWeight: 52,
        measuredReps: 10,
        oneRepMaxModifier: 100,
        targetPercentage: 7,
        startingReps: 10,
        sets: 4,
        weeks: 5,
    );

    expect($block->config->starting1RM)->toBeGreaterThan(0);
    expect($block->config->target1RM)->toBeGreaterThan($block->config->starting1RM);
});

it('applies oneRepMaxModifier correctly', function () {
    $generator = new TrainingBlockGenerator;

    $blockFull = $generator->generate(
        measuredWeight: 100,
        measuredReps: 1,
        oneRepMaxModifier: 100,
        targetPercentage: 10,
        startingReps: 10,
        sets: 4,
        weeks: 5,
    );

    $blockModified = $generator->generate(
        measuredWeight: 100,
        measuredReps: 1,
        oneRepMaxModifier: 85,
        targetPercentage: 10,
        startingReps: 10,
        sets: 4,
        weeks: 5,
    );

    expect($blockModified->config->starting1RM)->toBeLessThan($blockFull->config->starting1RM);
    $expected = $blockFull->config->starting1RM * 0.85;
    expect(abs($blockModified->config->starting1RM - $expected))->toBeLessThan(1);
});

it('applies deload rule on odd weeks', function () {
    $generator = new TrainingBlockGenerator;

    $block = $generator->generate(
        measuredWeight: 52,
        measuredReps: 10,
        oneRepMaxModifier: 100,
        targetPercentage: 7,
        startingReps: 10,
        sets: 4,
        weeks: 5,
        deloadEnabled: true,
        deloadSetsReduction: 1,
    );

    expect(count($block->weeks[0]->sessions[0]->sets))->toBe(3);
    expect(count($block->weeks[1]->sessions[0]->sets))->toBe(4);
    expect(count($block->weeks[2]->sessions[0]->sets))->toBe(3);
    expect(count($block->weeks[3]->sessions[0]->sets))->toBe(4);
    expect(count($block->weeks[4]->sessions[0]->sets))->toBe(3);
});

it('does not apply deload when disabled', function () {
    $generator = new TrainingBlockGenerator;

    $block = $generator->generate(
        measuredWeight: 52,
        measuredReps: 10,
        oneRepMaxModifier: 100,
        targetPercentage: 7,
        startingReps: 10,
        sets: 4,
        weeks: 5,
        deloadEnabled: false,
    );

    foreach ($block->weeks as $week) {
        expect(count($week->sessions[0]->sets))->toBe(4);
    }
});
