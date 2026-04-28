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
