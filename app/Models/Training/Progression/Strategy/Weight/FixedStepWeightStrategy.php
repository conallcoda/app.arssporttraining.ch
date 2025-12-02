<?php

namespace App\Models\Training\Progression\Strategy\Weight;

use App\Models\Training\Progression\Config\ResolvedExerciseConfig;
use App\Models\Training\Progression\Reference\RepPercentageTable;
use App\Models\Training\Progression\Reference\WeightBracket;

class FixedStepWeightStrategy implements WeightStrategyInterface
{
    public function calculateAnchorWeight(
        ResolvedExerciseConfig $config,
        float $derived1RM,
        int $weekIndex,
    ): float {
        $finalAnchor = $this->calculateFinalAnchorWeight($config, $derived1RM);
        $weeksFromEnd = ($config->blockLength - 1) - $weekIndex;

        $anchor = $finalAnchor;
        for ($i = 0; $i < $weeksFromEnd; $i++) {
            $step = WeightBracket::getStepForWeight($anchor);
            $anchor -= $step;
        }

        return WeightBracket::roundToIncrement($anchor, $config->weightConfig->incrementStep);
    }

    public function calculateSetWeight(
        ResolvedExerciseConfig $config,
        float $anchorWeight,
        int $setIndex,
        int $totalSets,
    ): float {
        $anchorIndex = $totalSets - 1;
        $stepsFromAnchor = $anchorIndex - $setIndex;

        $weight = $anchorWeight;
        for ($i = 0; $i < $stepsFromAnchor; $i++) {
            $step = WeightBracket::getStepForWeight($weight);
            $weight -= $step;
        }

        return WeightBracket::roundToIncrement($weight, $config->weightConfig->incrementStep);
    }

    public function getProjected1RM(
        ResolvedExerciseConfig $config,
        float $derived1RM,
        int $weekIndex,
    ): float {
        $anchorWeight = $this->calculateAnchorWeight($config, $derived1RM, $weekIndex);
        $anchorReps = $config->getAnchorRepsForWeek($weekIndex);
        $percentage = RepPercentageTable::getPercentage($anchorReps);

        return $anchorWeight / $percentage;
    }

    public function getTarget1RM(
        ResolvedExerciseConfig $config,
        float $derived1RM,
    ): float {
        return $derived1RM * (1 + $config->weightConfig->getTargetImprovementAsDecimal());
    }

    protected function calculateFinalAnchorWeight(
        ResolvedExerciseConfig $config,
        float $derived1RM,
    ): float {
        $target1RM = $this->getTarget1RM($config, $derived1RM);
        $finalWeekIndex = $config->blockLength - 1;
        $finalAnchorReps = $config->getAnchorRepsForWeek($finalWeekIndex);
        $percentage = RepPercentageTable::getPercentage($finalAnchorReps);

        return WeightBracket::roundToIncrement($target1RM * $percentage, $config->weightConfig->incrementStep);
    }
}
