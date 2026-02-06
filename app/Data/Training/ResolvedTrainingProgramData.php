<?php

namespace App\Data\Training;

use App\Training\Reference\RepPercentageTable;

class ResolvedTrainingProgramData
{
    public function __construct(
        public int $userId,
        public string $startDate,
        public int $measuredReps,
        public float $measuredWeight,
        public int $targetGoal,
    ) {}

    public function estimatedOneRepMax(): ?float
    {
        if ($this->measuredWeight <= 0 || $this->measuredReps < 1) {
            return null;
        }

        $percentage = RepPercentageTable::getPercentage($this->measuredReps);

        return round($this->measuredWeight / $percentage, 1);
    }
}
