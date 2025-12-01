<?php

namespace App\Models\Training\Progression\Strategy\Weight;

use App\Models\Training\Progression\Config\ResolvedExerciseConfig;

interface WeightStrategyInterface
{
    public function calculateAnchorWeight(
        ResolvedExerciseConfig $config,
        float $derived1RM,
        int $weekIndex,
    ): float;

    public function calculateSetWeight(
        ResolvedExerciseConfig $config,
        float $anchorWeight,
        int $setIndex,
        int $totalSets,
    ): float;

    public function getProjected1RM(
        ResolvedExerciseConfig $config,
        float $derived1RM,
        int $weekIndex,
    ): float;

    public function getTarget1RM(
        ResolvedExerciseConfig $config,
        float $derived1RM,
    ): float;
}
