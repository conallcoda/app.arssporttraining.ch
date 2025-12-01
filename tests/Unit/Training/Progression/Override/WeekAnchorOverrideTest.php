<?php

use App\Models\Training\Progression\Override\WeekAnchorOverride;

describe('WeekAnchorOverride', function () {
    it('can be created with value only', function () {
        $override = new WeekAnchorOverride(value: 60.0);

        expect($override->value)->toBe(60.0);
        expect($override->scope)->toBe('single');
        expect($override->fromWeek)->toBe(0);
        expect($override->recalculateReps)->toBeFalse();
    });

    it('can be created with carry forward scope', function () {
        $override = new WeekAnchorOverride(
            value: 60.0,
            scope: 'carry_forward',
            fromWeek: 2,
        );

        expect($override->scope)->toBe('carry_forward');
        expect($override->fromWeek)->toBe(2);
        expect($override->isCarryForward())->toBeTrue();
        expect($override->isSingle())->toBeFalse();
    });

    it('detects single scope', function () {
        $override = new WeekAnchorOverride(value: 60.0, scope: 'single');

        expect($override->isSingle())->toBeTrue();
        expect($override->isCarryForward())->toBeFalse();
    });

    it('single scope only applies to its own week', function () {
        $override = new WeekAnchorOverride(
            value: 60.0,
            scope: 'single',
            fromWeek: 2,
        );

        expect($override->appliesTo(0))->toBeFalse();
        expect($override->appliesTo(1))->toBeFalse();
        expect($override->appliesTo(2))->toBeTrue();
        expect($override->appliesTo(3))->toBeFalse();
        expect($override->appliesTo(4))->toBeFalse();
    });

    it('carry forward scope applies to current and future weeks', function () {
        $override = new WeekAnchorOverride(
            value: 60.0,
            scope: 'carry_forward',
            fromWeek: 2,
        );

        expect($override->appliesTo(0))->toBeFalse();
        expect($override->appliesTo(1))->toBeFalse();
        expect($override->appliesTo(2))->toBeTrue();
        expect($override->appliesTo(3))->toBeTrue();
        expect($override->appliesTo(4))->toBeTrue();
    });

    it('can have recalculate reps flag', function () {
        $override = new WeekAnchorOverride(
            value: 60.0,
            scope: 'carry_forward',
            fromWeek: 2,
            recalculateReps: true,
        );

        expect($override->recalculateReps)->toBeTrue();
    });
});
