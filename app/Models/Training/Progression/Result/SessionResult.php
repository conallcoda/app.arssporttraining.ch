<?php

namespace App\Models\Training\Progression\Result;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class SessionResult extends AbstractData
{
    public function __construct(
        public string $sessionUuid,
        public int $day,
        public int $slot,
        #[DataCollectionOf(SetResult::class)]
        public array $sets = [],
    ) {}

    public function getSet(int $index): ?SetResult
    {
        return $this->sets[$index] ?? null;
    }

    public function setCount(): int
    {
        return count($this->sets);
    }

    public function isFullyLocked(): bool
    {
        if (empty($this->sets)) {
            return false;
        }

        foreach ($this->sets as $set) {
            if (! $set->isLocked()) {
                return false;
            }
        }

        return true;
    }

    public function hasOverrides(): bool
    {
        foreach ($this->sets as $set) {
            if ($set->isOverridden()) {
                return true;
            }
        }

        return false;
    }

    public function getTotalVolume(): float
    {
        $volume = 0;

        foreach ($this->sets as $set) {
            $volume += $set->reps * $set->weight;
        }

        return $volume;
    }
}
