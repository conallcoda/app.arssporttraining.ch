<?php

namespace App\Training\Data;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class TrainingSession extends AbstractData
{
    public function __construct(
        #[DataCollectionOf(TrainingSet::class)]
        public array $sets = [],
    ) {}

    public function mapSets(callable $transformer): self
    {
        return new self(
            sets: array_map($transformer, $this->sets, array_keys($this->sets))
        );
    }

    public function lastSet(): ?TrainingSet
    {
        return $this->sets[count($this->sets) - 1] ?? null;
    }

    public static function fromSetCount(int $count): self
    {
        $sets = [];
        for ($i = 0; $i < $count; $i++) {
            $sets[] = new TrainingSet;
        }

        return new self(sets: $sets);
    }
}
