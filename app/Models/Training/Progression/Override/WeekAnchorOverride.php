<?php

namespace App\Models\Training\Progression\Override;

use App\Data\AbstractData;

class WeekAnchorOverride extends AbstractData
{
    public function __construct(
        public float $value,
        public string $scope = 'single',
        public int $fromWeek = 0,
        public bool $recalculateReps = false,
    ) {}

    public function isSingle(): bool
    {
        return $this->scope === 'single';
    }

    public function isCarryForward(): bool
    {
        return $this->scope === 'carry_forward';
    }

    public function appliesTo(int $weekIndex): bool
    {
        if ($this->isSingle()) {
            return $weekIndex === $this->fromWeek;
        }

        return $weekIndex >= $this->fromWeek;
    }
}
