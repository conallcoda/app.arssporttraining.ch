<?php

namespace App\Livewire\Test\Data\Strategies\Weight;

class MeasuredData
{
    public function __construct(
        public ?int $measuredReps = null,
        public ?float $measuredWeight = null,
        public ?int $targetGoal = null,
    ) {}

    public function isComplete(): bool
    {
        return $this->measuredWeight !== null
            && $this->measuredWeight > 0
            && $this->measuredReps !== null
            && $this->measuredReps >= 1;
    }
}
