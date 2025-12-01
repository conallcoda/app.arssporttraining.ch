<?php

namespace App\Models\Training\Progression\Config;

use App\Data\AbstractData;
use App\Models\Training\Progression\Reference\RepPercentageTable;

class ResolvedExerciseConfig extends AbstractData
{
    public function __construct(
        public int $exerciseId,
        public string $weightStrategy,
        public string $repStrategy,
        public float $targetImprovement,
        public int $startingReps,
        public int $stepDownInterval,
        public int $repDecrement,
        public int $minimumReps,
        public float $incrementStep,
        public int $blockLength,
        public float $modifier = 1.0,
    ) {}

    public function getAnchorRepsForWeek(int $weekIndex): int
    {
        $reps = $this->startingReps - $this->repDecrement;
        $drops = intdiv($weekIndex, $this->stepDownInterval);
        $reps -= ($drops * $this->repDecrement);

        return max($reps, $this->minimumReps);
    }

    public function getSetCountForWeek(int $weekIndex): int
    {
        return ($weekIndex % 2 === 0) ? 3 : 4;
    }

    public function getRepPercentage(int $reps): float
    {
        return RepPercentageTable::getPercentage($reps);
    }

    public function getTarget1RM(float $derived1RM): float
    {
        return $derived1RM * (1 + $this->targetImprovement);
    }
}
