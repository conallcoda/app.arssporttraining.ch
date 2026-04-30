<?php

use App\Data\Exercise\Preview\GridOverrides;
use Tests\TestCase;

uses(TestCase::class);

it('resolves session-specific cell overrides without leaking them to other sessions', function () {
    $overrides = GridOverrides::fromArrays([
        ['week' => 0, 'session' => 1, 'set' => 0, 'data' => ['reps' => 8]],
    ], []);

    expect($overrides->getCellOverrideValue(0, 0, 'reps', 0))->toBeNull()
        ->and($overrides->getCellOverrideValue(0, 0, 'reps', 1))->toBe(8)
        ->and($overrides->hasCellOverride(0, 0, 'reps', 0))->toBeFalse()
        ->and($overrides->hasCellOverride(0, 0, 'reps', 1))->toBeTrue();
});

it('does not apply session 0 overrides to later sessions without an explicit row', function () {
    $overrides = GridOverrides::fromArrays([
        ['week' => 2, 'session' => 0, 'set' => 1, 'data' => ['weight' => 15]],
    ], []);

    expect($overrides->getCellOverrideValue(2, 1, 'weight', 0))->toBe(15)
        ->and($overrides->getCellOverrideValue(2, 1, 'weight', 1))->toBeNull()
        ->and($overrides->hasCellOverride(2, 1, 'weight', 1))->toBeFalse();
});

it('keeps later session rows isolated even when session 0 has a different override', function () {
    $overrides = GridOverrides::fromArrays([
        ['week' => 2, 'session' => 0, 'set' => 1, 'data' => ['weight' => 15]],
        ['week' => 2, 'session' => 1, 'set' => 1, 'data' => ['reps' => 8]],
    ], []);

    expect($overrides->getCellOverrideValue(2, 1, 'weight', 1))->toBeNull()
        ->and($overrides->hasCellOverride(2, 1, 'weight', 1))->toBeFalse()
        ->and($overrides->getCellOverrideValue(2, 1, 'reps', 1))->toBe(8)
        ->and($overrides->hasCellOverride(2, 1, 'reps', 1))->toBeTrue();
});
