<?php

namespace App\Training\Derivation;

use App\Data\Exercise\BilateralReps;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Exercise\Settings\WeightSetting;
use App\Training\Reference\OneRepMaxConversion;
use App\Training\Reference\RepPercentageTable;

class AutomaticWeightResolver
{
    public function resolve(
        WeightSetting $setting,
        WeightProgressionSetting $measuredData,
        int $weeks,
        array $setsPerWeek,
        callable $resolvedRepsForCell,
    ): ?AutomaticStrategyResolution {
        $resolution = $this->buildResolution($setting, $measuredData, $weeks, $setsPerWeek, $resolvedRepsForCell);

        if ($resolution === null) {
            return null;
        }

        return new AutomaticStrategyResolution([
            'weight' => new ResolvedGridField(
                grid: $resolution->weights,
                metadata: ['summary' => $resolution->summary],
            ),
            'oneRepMax' => new ResolvedGridField(
                grid: $resolution->oneRepMax,
            ),
        ]);
    }

    public function buildResolution(
        WeightSetting $setting,
        WeightProgressionSetting $measuredData,
        int $weeks,
        array $setsPerWeek,
        callable $resolvedRepsForCell,
    ): ?AutomaticWeightResolution {
        if (! $measuredData->isComplete()) {
            return null;
        }

        $target1RM = $this->calculateTargetOneRepMax($setting, $measuredData);
        $weekTargets = $this->calculateWeekTargets($setting, $measuredData, $target1RM, $weeks, $setsPerWeek, $resolvedRepsForCell);

        $weights = [];
        $oneRepMax = [];
        $lastWeekIndex = $weeks - 1;

        for ($week = 0; $week < $weeks; $week++) {
            $setCount = $setsPerWeek[$week];
            $setWeights = $this->calculateSetWeights($weekTargets[$week], $setCount);
            $lastSetIndex = $setCount - 1;

            $weights[$week] = [];
            $oneRepMax[$week] = [];

            for ($set = 0; $set < $setCount; $set++) {
                $weights[$week][$set] = self::roundWeight($setWeights[$set]);

                $isLastSetOfLastWeek = $week === $lastWeekIndex && $set === $lastSetIndex;
                $oneRepMax[$week][$set] = $isLastSetOfLastWeek
                    ? round($target1RM, 1)
                    : '-';
            }
        }

        return new AutomaticWeightResolution(
            weights: $weights,
            oneRepMax: $oneRepMax,
            summary: $this->buildSummary($setting, $measuredData, $target1RM),
        );
    }

    /** @return array{starting1RM: float, target1RM: float, targetGoal: int|float} */
    private function buildSummary(WeightSetting $setting, WeightProgressionSetting $measuredData, float $target1RM): array
    {
        $starting1RM = OneRepMaxConversion::estimatedOneRepMax(
            $measuredData->measuredReps,
            $measuredData->measuredWeight,
            $setting->oneRepMaxModifier ?? 100,
        );

        return [
            'starting1RM' => $starting1RM,
            'target1RM' => round($target1RM, 1),
            'targetGoal' => $measuredData->targetGoal ?? 0,
        ];
    }

    private function calculateTargetOneRepMax(WeightSetting $setting, WeightProgressionSetting $measuredData): float
    {
        $starting1RM = OneRepMaxConversion::estimatedOneRepMax(
            $measuredData->measuredReps,
            $measuredData->measuredWeight,
            $setting->oneRepMaxModifier ?? 100,
        );

        return OneRepMaxConversion::targetOneRepMax($starting1RM, $measuredData->targetGoal ?? 0);
    }

    /** @return array<int, float> */
    private function calculateWeekTargets(
        WeightSetting $setting,
        WeightProgressionSetting $measuredData,
        float $target1RM,
        int $weeks,
        array $setsPerWeek,
        callable $resolvedRepsForCell,
    ): array {
        $targets = [];
        $lastWeekIndex = $weeks - 1;
        $lastSetIndex = $setsPerWeek[$lastWeekIndex] - 1;

        $lastSetReps = $resolvedRepsForCell($lastWeekIndex, $lastSetIndex);
        $totalReps = BilateralReps::parse($lastSetReps ?? 1)->total();
        $repPercentage = RepPercentageTable::getPercentage($totalReps);
        $anchorWeight = $target1RM * $repPercentage;

        $targets[$lastWeekIndex] = $anchorWeight;
        $currentWeight = $anchorWeight;

        for ($week = $lastWeekIndex - 1; $week >= 0; $week--) {
            $currentWeight = self::decrementWeight($currentWeight);
            $targets[$week] = $currentWeight;
        }

        ksort($targets);

        return $targets;
    }

    /** @return array<int, float> */
    private function calculateSetWeights(float $weekTarget, int $setCount): array
    {
        $weights = [];

        for ($set = 0; $set < $setCount; $set++) {
            $groupFromEnd = $setCount - 1 - $set;
            $weight = $weekTarget;

            for ($i = 0; $i < $groupFromEnd; $i++) {
                $weight = self::decrementWeight($weight);
            }

            $weights[] = $weight;
        }

        return $weights;
    }

    private static function stepForWeight(float $weight): float
    {
        if ($weight > 107.5) {
            return 7.5;
        }

        if ($weight >= 55) {
            return 5.0;
        }

        return 2.5;
    }

    private static function decrementWeight(float $weight): float
    {
        return max(0, $weight - self::stepForWeight($weight));
    }

    private static function roundWeight(float $weight, float $step = 0.5): float
    {
        return round($weight / $step) * $step;
    }
}
