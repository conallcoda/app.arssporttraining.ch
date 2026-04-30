<?php

namespace App\Training\Derivation;

class AutomaticWeightResolution
{
    /**
     * @param  array<int, array<int, float>>  $weights
     * @param  array<int, array<int, string|float>>  $oneRepMax
     * @param  array<int, array<int, array<int, float>>>  $weightSessionGrid
     * @param  array<int, array<int, array<int, string|float>>>  $oneRepMaxSessionGrid
     * @param  array{starting1RM: float, target1RM: float, targetGoal: int|float}  $summary
     */
    public function __construct(
        public array $weights,
        public array $oneRepMax,
        public array $summary,
        public array $weightSessionGrid = [],
        public array $oneRepMaxSessionGrid = [],
    ) {}
}
