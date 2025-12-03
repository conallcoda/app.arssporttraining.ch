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
                AthleteTestData::back_squat(),
            ],
        );
    }
}
