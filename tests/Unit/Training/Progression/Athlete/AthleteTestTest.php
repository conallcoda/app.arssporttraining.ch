<?php

use App\Models\Training\Progression\Athlete\AthleteTest;
use Carbon\Carbon;

describe('AthleteTest', function () {
    it('can be created with required fields', function () {
        $test = new AthleteTest(
            exerciseId: 1,
            reps: 8,
            weight: 56.0,
        );

        expect($test->exerciseId)->toBe(1);
        expect($test->reps)->toBe(8);
        expect($test->weight)->toBe(56.0);
        expect($test->testedAt)->toBeNull();
    });

    it('can be created with tested at date', function () {
        $date = Carbon::now();
        $test = new AthleteTest(
            exerciseId: 1,
            reps: 8,
            weight: 56.0,
            testedAt: $date,
        );

        expect($test->testedAt)->toBe($date);
    });

    it('calculates derived 1RM from test result', function () {
        $test = new AthleteTest(
            exerciseId: 1,
            reps: 8,
            weight: 56.0,
        );

        $derived1RM = $test->getDerived1RM();

        expect($derived1RM)->toBe(70.0);
    });

    it('calculates derived 1RM for different rep counts', function () {
        $test6Reps = new AthleteTest(exerciseId: 1, reps: 6, weight: 60.2);
        expect($test6Reps->getDerived1RM())->toBeGreaterThan(69.0)->toBeLessThan(71.0);

        $test10Reps = new AthleteTest(exerciseId: 1, reps: 10, weight: 51.8);
        expect($test10Reps->getDerived1RM())->toBeGreaterThan(69.0)->toBeLessThan(71.0);

        $test12Reps = new AthleteTest(exerciseId: 1, reps: 12, weight: 46.9);
        expect($test12Reps->getDerived1RM())->toBeGreaterThan(69.0)->toBeLessThan(71.0);
    });
});
