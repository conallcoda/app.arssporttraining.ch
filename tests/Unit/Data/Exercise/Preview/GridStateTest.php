<?php

use App\Data\Exercise\Preview\GridState;

it('stores and retrieves sets per week', function () {
    $state = new GridState;
    $setsPerWeek = [3, 4, 3, 4, 3];

    $state->setSetsPerWeek($setsPerWeek);

    expect($state->getSetsPerWeek())->toBe($setsPerWeek);
    expect($state->hasSetsPerWeek())->toBeTrue();
});

it('reports empty when no sets per week set', function () {
    $state = new GridState;

    expect($state->hasSetsPerWeek())->toBeFalse();
    expect($state->getSetsPerWeek())->toBe([]);
});

it('calculates max sets', function () {
    $state = new GridState;
    $state->setSetsPerWeek([3, 4, 3, 4, 3]);

    expect($state->maxSets())->toBe(4);
});

it('returns zero max sets when empty', function () {
    $state = new GridState;

    expect($state->maxSets())->toBe(0);
});

it('stores and retrieves grids by setting name', function () {
    $state = new GridState;
    $repsGrid = [
        [10, 10, 8],
        [10, 10, 8, 8],
    ];

    $state->setGrid('reps', $repsGrid);

    expect($state->getGrid('reps'))->toBe($repsGrid);
    expect($state->hasGrid('reps'))->toBeTrue();
});

it('returns null for missing grids', function () {
    $state = new GridState;

    expect($state->getGrid('weight'))->toBeNull();
    expect($state->hasGrid('weight'))->toBeFalse();
});

it('retrieves individual cell values', function () {
    $state = new GridState;
    $state->setGrid('reps', [
        [10, 10, 8],
        [8, 8, 6],
    ]);

    expect($state->getCellValue('reps', 0, 2))->toBe(8);
    expect($state->getCellValue('reps', 1, 1))->toBe(8);
    expect($state->getCellValue('reps', 1, 2))->toBe(6);
});

it('returns null for out of bounds cell values', function () {
    $state = new GridState;
    $state->setGrid('reps', [[10, 10]]);

    expect($state->getCellValue('reps', 0, 5))->toBeNull();
    expect($state->getCellValue('reps', 5, 0))->toBeNull();
    expect($state->getCellValue('weight', 0, 0))->toBeNull();
});

it('stores and retrieves metadata', function () {
    $state = new GridState;
    $summary = ['starting1RM' => 60.5, 'target1RM' => 69.6, 'targetGoal' => 15];

    $state->setMetadata('weight', 'summary', $summary);

    expect($state->getMetadata('weight', 'summary'))->toBe($summary);
});

it('returns null for missing metadata', function () {
    $state = new GridState;

    expect($state->getMetadata('weight', 'summary'))->toBeNull();
});

it('returns all stored grids', function () {
    $state = new GridState;
    $state->setGrid('reps', [[10, 10]]);
    $state->setGrid('weight', [[52.0, 47.0]]);

    $grids = $state->getGrids();

    expect($grids)->toHaveCount(2);
    expect($grids)->toHaveKeys(['reps', 'weight']);
});
