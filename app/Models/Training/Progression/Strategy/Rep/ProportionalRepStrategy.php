<?php

namespace App\Models\Training\Progression\Strategy\Rep;

use App\Models\Training\Progression\Config\ResolvedExerciseConfig;
use App\Models\Training\Progression\Reference\RepPercentageTable;

class ProportionalRepStrategy implements RepStrategyInterface
{
    public function calculateSetReps(
        ResolvedExerciseConfig $config,
        int $weekIndex,
        int $setIndex,
        int $totalSets,
        ?float $setWeight = null,
        ?float $reference1RM = null,
    ): int {
        $anchorIndex = $totalSets - 1;

        if ($setIndex === $anchorIndex) {
            return $config->getAnchorRepsForWeek($weekIndex);
        }

        if ($setWeight === null || $reference1RM === null || $reference1RM <= 0) {
            return $config->getAnchorRepsForWeek($weekIndex);
        }

        $percentage = $setWeight / $reference1RM;
        $reps = RepPercentageTable::getRepsRounded($percentage, 2);

        return max($reps, $config->repConfig->minimumReps);
    }

    public function getReference1RM(
        ResolvedExerciseConfig $config,
        float $derived1RM,
        float $target1RM,
        int $weekIndex,
    ): float {
        if ($config->blockLength <= 1) {
            return $derived1RM;
        }

        $progress = $weekIndex / ($config->blockLength - 1);

        return $derived1RM + (($target1RM - $derived1RM) * $progress);
    }

    public function calculateRepsFromPercentage(float $percentage, int $minimumReps): int
    {
        $reps = RepPercentageTable::getRepsRounded($percentage, 2);

        return max($reps, $minimumReps);
    }
}
