<?php

use App\Models\Training\Progression\Athlete\AthleteData;
use App\Models\Training\Progression\Athlete\AthleteTest;
use Tests\Unit\Training\Progression\ProgressionTestHelpers;

uses(ProgressionTestHelpers::class);

describe('AthleteData', function () {
    it('can be created with athlete id', function () {
        $data = new AthleteData(athleteId: 1);

        expect($data->athleteId)->toBe(1);
        expect($data->tests)->toBe([]);
    });

    it('can be created with tests', function () {
        $test = new AthleteTest(exerciseId: 1, reps: 8, weight: 56.0);
        $data = new AthleteData(
            athleteId: 1,
            tests: [1 => $test],
        );

        expect($data->tests)->toHaveCount(1);
        expect($data->tests[1])->toBe($test);
    });

    it('can get test by exercise id', function () {
        $data = $this->createDefaultAthleteData();

        $test = $data->getTest(1);

        expect($test)->toBeInstanceOf(AthleteTest::class);
        expect($test->exerciseId)->toBe(1);
        expect($test->reps)->toBe(8);
        expect($test->weight)->toBe(56.0);
    });

    it('returns null for non-existent test', function () {
        $data = $this->createDefaultAthleteData();

        expect($data->getTest(999))->toBeNull();
    });

    it('can set test', function () {
        $data = new AthleteData(athleteId: 1);
        $test = new AthleteTest(exerciseId: 2, reps: 10, weight: 40.0);

        $data->setTest($test);

        expect($data->hasTest(2))->toBeTrue();
        expect($data->getTest(2))->toBe($test);
    });

    it('can check if test exists', function () {
        $data = $this->createDefaultAthleteData();

        expect($data->hasTest(1))->toBeTrue();
        expect($data->hasTest(999))->toBeFalse();
    });

    it('calculates derived 1RM for exercise', function () {
        $data = $this->createDefaultAthleteData();

        $derived1RM = $data->getDerived1RM(1);

        expect($derived1RM)->toBe(70.0);
    });

    it('calculates derived 1RM with modifier', function () {
        $data = $this->createDefaultAthleteData();

        $derived1RM = $data->getDerived1RM(1, 0.85);

        expect($derived1RM)->toBe(59.5);
    });

    it('returns null for derived 1RM when test does not exist', function () {
        $data = $this->createDefaultAthleteData();

        expect($data->getDerived1RM(999))->toBeNull();
    });
});
