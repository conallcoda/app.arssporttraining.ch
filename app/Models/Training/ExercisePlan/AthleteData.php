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

    public static function example($id = 1, $name = 'John Doe', $reps = 8, $weight = 45): self
    {
        return new self(
            id: $id,
            name: $name,
            tests: [
                AthleteTestData::back_squat($reps, $weight),
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
