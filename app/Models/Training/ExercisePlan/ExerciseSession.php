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

    public function mapSets(callable $transformer): self
    {
        return new self(
            sets: array_map($transformer, $this->sets, array_keys($this->sets))
        );
    }

    public function lastSet(): ?ExerciseSet
    {
        return $this->sets[count($this->sets) - 1] ?? null;
    }

    public function lastSetIndex(): int
    {
        return max(0, count($this->sets) - 1);
    }

    public static function fromSetCount(int $count): self
    {
        $sets = [];
        for ($i = 0; $i < $count; $i++) {
            $sets[] = new ExerciseSet;
        }

        return new self(sets: $sets);
    }

    public static function example(): self
    {
        return self::fromSetCount(4);
    }
}
