<?php

namespace App\Models\Training\Progression\Strategy\Weight;

interface WeightConfigInterface
{
    public float $targetImprovement { get; }

    public function getStrategyClass(): string;

    public function getTargetImprovementAsDecimal(): float;
}
