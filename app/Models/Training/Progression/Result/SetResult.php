<?php

namespace App\Models\Training\Progression\Result;

use App\Data\AbstractData;

class SetResult extends AbstractData
{
    public function __construct(
        public int $setIndex,
        public int $reps,
        public float $weight,
        public string $source = 'computed',
        public ?int $computedReps = null,
        public ?float $computedWeight = null,
    ) {}

    public function isComputed(): bool
    {
        return $this->source === 'computed';
    }

    public function isManual(): bool
    {
        return $this->source === 'manual';
    }

    public function isLocked(): bool
    {
        return $this->source === 'locked';
    }

    public function isOverridden(): bool
    {
        return $this->isManual() || $this->isLocked();
    }

    public function hasRepOverride(): bool
    {
        return $this->computedReps !== null && $this->reps !== $this->computedReps;
    }

    public function hasWeightOverride(): bool
    {
        return $this->computedWeight !== null && $this->weight !== $this->computedWeight;
    }

    public function getRepDifference(): ?int
    {
        if ($this->computedReps === null) {
            return null;
        }

        return $this->reps - $this->computedReps;
    }

    public function getWeightDifference(): ?float
    {
        if ($this->computedWeight === null) {
            return null;
        }

        return $this->weight - $this->computedWeight;
    }
}
