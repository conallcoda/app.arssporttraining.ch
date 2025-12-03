<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class ExerciseSession extends AbstractData
{
    public function __construct(
        #[DataCollectionOf(ExerciseSet::class)]
        public array $sets = [],
    ) {}

    public static function fromSetCount(int $count): self
    {
        $sets = [];
        for ($i = 0; $i < $count; $i++) {
            $sets[] = new ExerciseSet();
        }

        return new self(sets: $sets);
    }

    public static function example(): self
    {
        return self::fromSetCount(4);
    }
}
