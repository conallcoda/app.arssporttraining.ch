<?php

namespace App\Models\Training\Progression\Config;

use App\Data\AbstractData;

class BlockOverview extends AbstractData
{
    public function __construct(
        public int $weekCount,
        public int $totalSessions,
        public int $minSessionsPerWeek,
        public int $maxSessionsPerWeek,
        public string $weightStrategy,
        public string $repStrategy,
        public float $targetImprovement,
    ) {}

    public function getTargetImprovementLabel(): string
    {
        return number_format($this->targetImprovement, 1).'%';
    }

    public function getSessionsPerWeekLabel(): string
    {
        if ($this->minSessionsPerWeek === $this->maxSessionsPerWeek) {
            return (string) $this->minSessionsPerWeek;
        }

        return "{$this->minSessionsPerWeek} - {$this->maxSessionsPerWeek}";
    }
}
