<?php

namespace App\Models\Training\Progression\Strategy\Rep;

use App\Models\Training\Progression\Config\ResolvedExerciseConfig;

interface RepStrategyInterface
{
    public function calculateSetReps(
        ResolvedExerciseConfig $config,
        int $weekIndex,
        int $setIndex,
        int $totalSets,
        ?float $setWeight = null,
        ?float $reference1RM = null,
    ): int;

    public function getReference1RM(
        ResolvedExerciseConfig $config,
        float $derived1RM,
        float $target1RM,
        int $weekIndex,
    ): float;
}
