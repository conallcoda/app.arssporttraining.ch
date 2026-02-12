<?php

namespace App\Livewire\Test\Data\Strategies\Weight;

use App\Livewire\Test\Data\Settings\WeightSetting;

class OneRepMaxFixedStrategy
{
    private const REP_PERCENTAGE_TABLE = [
        1 => 1.00,
        2 => 0.96,
        3 => 0.94,
        4 => 0.91,
        5 => 0.88,
        6 => 0.86,
        7 => 0.83,
        8 => 0.80,
        9 => 0.77,
        10 => 0.74,
        11 => 0.71,
        12 => 0.67,
        13 => 0.65,
        14 => 0.63,
        15 => 0.62,
    ];

    public function __construct(
        private WeightSetting $setting,
        private MeasuredData $measuredData,
    ) {}

    /**
     * @param  array<int, int>  $setsPerWeek
     * @return array{weights: array<int, array<int, float>>, oneRepMax: array<int, array<int, string|float>>}|null
     */
    public function generate(int $weeks, array $setsPerWeek): ?array
    {
        if (! $this->measuredData->isComplete()) {
            return null;
        }

        $target1RM = $this->calculateTarget1RM();
        $weekTargets = $this->calculateWeekTargets($target1RM, $weeks);

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

        return [
            'weights' => $weights,
            'oneRepMax' => $oneRepMax,
        ];
    }

    /** @return array{starting1RM: float, target1RM: float, targetGoal: int}|null */
    public function getSummary(): ?array
    {
        if (! $this->measuredData->isComplete()) {
            return null;
        }

        $repPercentage = self::repPercentage($this->measuredData->measuredReps);
        $estimated1RM = $this->measuredData->measuredWeight / $repPercentage;
        $exercise1RM = $estimated1RM * ($this->setting->oneRepMaxModifier / 100);
        $target1RM = $exercise1RM * (1 + ($this->measuredData->targetGoal ?? 0) / 100);

        return [
            'starting1RM' => round($exercise1RM, 1),
            'target1RM' => round($target1RM, 1),
            'targetGoal' => $this->measuredData->targetGoal ?? 0,
        ];
    }

    private function calculateTarget1RM(): float
    {
        $repPercentage = self::repPercentage($this->measuredData->measuredReps);
        $estimated1RM = $this->measuredData->measuredWeight / $repPercentage;
        $exercise1RM = $estimated1RM * ($this->setting->oneRepMaxModifier / 100);

        return $exercise1RM * (1 + ($this->measuredData->targetGoal ?? 0) / 100);
    }

    /** @return array<int, float> */
    private function calculateWeekTargets(float $target1RM, int $weeks): array
    {
        $targets = [];
        $lastWeekIndex = $weeks - 1;
        $targets[$lastWeekIndex] = $target1RM;

        $currentWeight = $target1RM;

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

    private static function repPercentage(int $reps): float
    {
        $reps = max(1, min(15, $reps));

        return self::REP_PERCENTAGE_TABLE[$reps];
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
