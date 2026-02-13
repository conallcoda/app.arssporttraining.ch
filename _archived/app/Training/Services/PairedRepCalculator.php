<?php

namespace App\Training\Services;

class PairedRepCalculator
{
    public function __construct(
        public int $startingReps = 10,
        public int $stepDownInterval = 2,
        public int $repDecrement = 2,
        public int $minimumReps = 1,
    ) {}

    public function getRepsForSet(int $weekIndex, int $setIndex): int
    {
        $anchorReps = $this->getAnchorRepsForWeek($weekIndex);
        $firstTierReps = $anchorReps + $this->repDecrement;

        return max($firstTierReps - (intdiv($setIndex, 2) * $this->repDecrement), $this->minimumReps);
    }

    public function getRepsForWeek(int $weekIndex, int $setCount): array
    {
        $reps = [];
        for ($setIndex = 0; $setIndex < $setCount; $setIndex++) {
            $reps[] = $this->getRepsForSet($weekIndex, $setIndex);
        }

        return $reps;
    }

    protected function getAnchorRepsForWeek(int $weekIndex): int
    {
        $reps = $this->startingReps - $this->repDecrement;
        $drops = intdiv($weekIndex, $this->stepDownInterval);
        $reps -= ($drops * $this->repDecrement);

        return max($reps, $this->minimumReps);
    }
}
