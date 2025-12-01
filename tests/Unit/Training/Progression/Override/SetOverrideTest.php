<?php

use App\Models\Training\Progression\Override\SetOverride;
use Carbon\Carbon;

describe('SetOverride', function () {
    it('can be created with defaults', function () {
        $override = new SetOverride;

        expect($override->reps)->toBeNull();
        expect($override->weight)->toBeNull();
        expect($override->locked)->toBeFalse();
        expect($override->lockedAt)->toBeNull();
    });

    it('can be created with reps override', function () {
        $override = new SetOverride(reps: 10);

        expect($override->reps)->toBe(10);
        expect($override->hasRepsOverride())->toBeTrue();
        expect($override->hasWeightOverride())->toBeFalse();
        expect($override->hasAnyOverride())->toBeTrue();
    });

    it('can be created with weight override', function () {
        $override = new SetOverride(weight: 55.0);

        expect($override->weight)->toBe(55.0);
        expect($override->hasRepsOverride())->toBeFalse();
        expect($override->hasWeightOverride())->toBeTrue();
        expect($override->hasAnyOverride())->toBeTrue();
    });

    it('can be created with both overrides', function () {
        $override = new SetOverride(reps: 10, weight: 55.0);

        expect($override->hasRepsOverride())->toBeTrue();
        expect($override->hasWeightOverride())->toBeTrue();
        expect($override->hasAnyOverride())->toBeTrue();
    });

    it('detects no overrides', function () {
        $override = new SetOverride;

        expect($override->hasAnyOverride())->toBeFalse();
    });

    it('can be locked', function () {
        $override = new SetOverride(reps: 10, weight: 55.0);

        $override->lock();

        expect($override->locked)->toBeTrue();
        expect($override->lockedAt)->toBeInstanceOf(Carbon::class);
    });

    it('can be unlocked', function () {
        $override = new SetOverride(reps: 10, weight: 55.0, locked: true, lockedAt: Carbon::now());

        $override->unlock();

        expect($override->locked)->toBeFalse();
        expect($override->lockedAt)->toBeNull();
    });

    it('can be created already locked', function () {
        $lockedAt = Carbon::now();
        $override = new SetOverride(
            reps: 10,
            weight: 55.0,
            locked: true,
            lockedAt: $lockedAt,
        );

        expect($override->locked)->toBeTrue();
        expect($override->lockedAt)->toBe($lockedAt);
    });
});
