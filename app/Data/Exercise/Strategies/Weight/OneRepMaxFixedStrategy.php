<?php

namespace App\Data\Exercise\Strategies\Weight;

use App\Data\Exercise\Preview\DefinesEditability;
use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Exercise\Settings\WeightSetting;
use App\Training\Reference\OneRepMaxConversion;
use App\Training\Reference\RepPercentageTable;

class OneRepMaxFixedStrategy implements DefinesEditability
{
    public function __construct(
        private WeightSetting $setting,
        private WeightProgressionSetting $measuredData,
    ) {}

    public function isEditable(string $field, int $week, int $set, GridState $state): bool
    {
        return $field !== 'oneRepMax' && $field !== 'weight';
    }

    /**
     * @return array{weights: array<int, array<int, float>>, oneRepMax: array<int, array<int, string|float>>}|null
     */
    public function generate(int $weeks, GridState $state): ?array
    {
        if (! $this->measuredData->isComplete()) {
            return null;
        }

        $setsPerWeek = $state->getSetsPerWeek();
        $target1RM = $this->calculateTarget1RM();
        $weekTargets = $this->calculateWeekTargets($target1RM, $weeks, $setsPerWeek, $state);

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

        $state->setGrid('weight', $weights);
        $state->setGrid('oneRepMax', $oneRepMax);
        $state->setMetadata('weight', 'summary', $this->buildSummary($target1RM));

        return [
            'weights' => $weights,
            'oneRepMax' => $oneRepMax,
        ];
    }

    /** @return array{starting1RM: float, target1RM: float, targetGoal: int} */
    private function buildSummary(float $target1RM): array
    {
        $starting1RM = OneRepMaxConversion::estimatedOneRepMax(
            $this->measuredData->measuredReps,
            $this->measuredData->measuredWeight,
            $this->setting->oneRepMaxModifier,
        );

        return [
            'starting1RM' => $starting1RM,
            'target1RM' => round($target1RM, 1),
            'targetGoal' => $this->measuredData->targetGoal ?? 0,
        ];
    }

    private function calculateTarget1RM(): float
    {
        $starting1RM = OneRepMaxConversion::estimatedOneRepMax(
            $this->measuredData->measuredReps,
            $this->measuredData->measuredWeight,
            $this->setting->oneRepMaxModifier,
        );

        return OneRepMaxConversion::targetOneRepMax($starting1RM, $this->measuredData->targetGoal ?? 0);
    }

    /**
     * @param  array<int, int>  $setsPerWeek
     * @return array<int, float>
     */
    private function calculateWeekTargets(float $target1RM, int $weeks, array $setsPerWeek, GridState $state): array
    {
        $targets = [];
        $lastWeekIndex = $weeks - 1;
        $lastSetIndex = $setsPerWeek[$lastWeekIndex] - 1;

        $lastSetReps = $state->getResolvedCellValue('reps', $lastWeekIndex, $lastSetIndex);
        $repPercentage = RepPercentageTable::getPercentage((int) ($lastSetReps ?? 1));
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
