<?php

namespace App\Models\Training\Progression\Strategy\Weight;

use App\Models\Training\Progression\Config\ResolvedExerciseConfig;
use App\Models\Training\Progression\Reference\RepPercentageTable;
use App\Models\Training\Progression\Reference\WeightBracket;

class CompoundedWeightStrategy implements WeightStrategyInterface
{
    public function calculateAnchorWeight(
        ResolvedExerciseConfig $config,
        float $derived1RM,
        int $weekIndex,
    ): float {
        $projected1RM = $this->getProjected1RM($config, $derived1RM, $weekIndex);
        $anchorReps = $config->getAnchorRepsForWeek($weekIndex);
        $percentage = RepPercentageTable::getPercentage($anchorReps);

        return WeightBracket::roundToIncrement($projected1RM * $percentage, $config->incrementStep);
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

        return WeightBracket::roundToIncrement($weight, $config->incrementStep);
    }

    public function getProjected1RM(
        ResolvedExerciseConfig $config,
        float $derived1RM,
        int $weekIndex,
    ): float {
        $weeklyGrowthRate = $this->calculateWeeklyGrowthRate($config);

        return $derived1RM * pow(1 + $weeklyGrowthRate, $weekIndex);
    }

    public function getTarget1RM(
        ResolvedExerciseConfig $config,
        float $derived1RM,
    ): float {
        return $derived1RM * (1 + $config->targetImprovement);
    }

    public function calculateWeeklyGrowthRate(ResolvedExerciseConfig $config): float
    {
        $intervals = $config->blockLength - 1;

        return pow(1 + $config->targetImprovement, 1 / $intervals) - 1;
    }

    public function recalculateFromOverride(
        ResolvedExerciseConfig $config,
        float $overrideAnchor,
        int $overrideWeekIndex,
        int $targetWeekIndex,
    ): float {
        $anchorReps = $config->getAnchorRepsForWeek($overrideWeekIndex);
        $percentage = RepPercentageTable::getPercentage($anchorReps);
        $implied1RM = $overrideAnchor / $percentage;

        $remainingWeeks = $config->blockLength - $overrideWeekIndex;
        $weeksToTarget = $targetWeekIndex - $overrideWeekIndex;

        if ($remainingWeeks <= 0 || $weeksToTarget <= 0) {
            return $implied1RM;
        }

        $adjustedGrowthRate = $this->calculateWeeklyGrowthRate($config);

        return $implied1RM * pow(1 + $adjustedGrowthRate, $weeksToTarget);
    }
}
