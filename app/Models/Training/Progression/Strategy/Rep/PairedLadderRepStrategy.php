<?php

namespace App\Models\Training\Progression\Strategy\Rep;

use App\Models\Training\Progression\Config\ResolvedExerciseConfig;

class PairedLadderRepStrategy implements RepStrategyInterface
{
    public function calculateSetReps(
        ResolvedExerciseConfig $config,
        int $weekIndex,
        int $setIndex,
        int $totalSets,
        ?float $setWeight = null,
        ?float $reference1RM = null,
    ): int {
        $anchorReps = $config->getAnchorRepsForWeek($weekIndex);
        $firstTierReps = $anchorReps + $config->repDecrement;

        $pairIndex = intdiv($setIndex, 2);
        $reps = $firstTierReps - ($pairIndex * $config->repDecrement);

        return max($reps, $config->minimumReps);
    }

    public function getReference1RM(
        ResolvedExerciseConfig $config,
        float $derived1RM,
        float $target1RM,
        int $weekIndex,
    ): float {
        return $derived1RM;
    }

    public function getRepPattern(ResolvedExerciseConfig $config, int $weekIndex): array
    {
        $totalSets = $config->getSetCountForWeek($weekIndex);
        $pattern = [];

        for ($i = 0; $i < $totalSets; $i++) {
            $pattern[] = $this->calculateSetReps($config, $weekIndex, $i, $totalSets);
        }

        return $pattern;
    }

    public function getFullBlockPattern(ResolvedExerciseConfig $config): array
    {
        $pattern = [];

        for ($week = 0; $week < $config->blockLength; $week++) {
            $pattern[$week] = $this->getRepPattern($config, $week);
        }

        return $pattern;
    }
}
