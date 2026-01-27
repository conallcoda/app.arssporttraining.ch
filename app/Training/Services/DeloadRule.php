<?php

namespace App\Training\Services;

class DeloadRule
{
    public function __construct(
        public int $setsToReduceBy = 1,
    ) {}

    public function isDeloadWeek(int $weekIndex): bool
    {
        $weekNumber = $weekIndex + 1;

        return $weekNumber % 2 === 1;
    }

    public function getSetsForWeek(int $weekIndex, int $baseSets): int
    {
        if ($this->isDeloadWeek($weekIndex)) {
            return max(0, $baseSets - $this->setsToReduceBy);
        }

        return $baseSets;
    }
}
