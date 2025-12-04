<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class AthleteData extends AbstractData
{
    public function __construct(
        public int $id,
        public string $name,
        #[DataCollectionOf(AthleteTestData::class)]
        public array $tests = [],

    ) {}

    public static function example(): self
    {
        return new self(
            id: 1,
            name: 'John Doe',
            tests: [
                AthleteTestData::back_squat(8, 45),
            ],
        );
    }

    public static function strong_doe(): self
    {
        return new self(
            id: 2,
            name: 'Strong Doe',
            tests: [
                AthleteTestData::back_squat(8, 90),
            ],
        );
    }

    public function getOneRepMaxForExercise(ExerciseData $exercise): float
    {
        foreach ($this->tests as $test) {
            if ($test->exerciseId === $exercise->id) {
                return $test->oneRepMax;
            }
        }

        foreach ($this->tests as $test) {
            if ($test->exerciseId === 1) {
                return $test->oneRepMax * ($exercise->modifier / 100);
            }
        }

        return 0.0;
    }
}
