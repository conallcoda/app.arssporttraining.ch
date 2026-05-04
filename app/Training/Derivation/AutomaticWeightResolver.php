<?php

namespace App\Training\Derivation;

use App\Data\Exercise\BilateralReps;
use App\Data\Exercise\Preview\SessionGroupBuilder;
use App\Data\Exercise\Preview\SessionGroupingMode;
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
        array $sessionCounts = [],
        ?string $groupingMode = null,
        ?int $groupSize = null,
        ?callable $resolvedSetsForSession = null,
    ): ?AutomaticStrategyResolution {
        $groupingMode = SessionGroupingMode::tryFrom((string) $groupingMode)?->value ?? SessionGroupingMode::defaultMode();
        $groupSize = max(1, (int) ($groupSize ?? SessionGroupingMode::defaultGroupSize()));

        $resolution = $this->buildResolution(
            $setting,
            $measuredData,
            $weeks,
            $setsPerWeek,
            $resolvedRepsForCell,
            $sessionCounts,
            $groupingMode,
            $groupSize,
            $resolvedSetsForSession,
        );

        if ($resolution === null) {
            return null;
        }

        return new AutomaticStrategyResolution([
            'weight' => new ResolvedGridField(
                grid: $resolution->weights,
                sessionGrid: $resolution->weightSessionGrid,
                metadata: ['summary' => $resolution->summary],
            ),
            'oneRepMax' => new ResolvedGridField(
                grid: $resolution->oneRepMax,
                sessionGrid: $resolution->oneRepMaxSessionGrid,
            ),
        ]);
    }

    public function buildResolution(
        WeightSetting $setting,
        WeightProgressionSetting $measuredData,
        int $weeks,
        array $setsPerWeek,
        callable $resolvedRepsForCell,
        array $sessionCounts = [],
        ?string $groupingMode = null,
        ?int $groupSize = null,
        ?callable $resolvedSetsForSession = null,
    ): ?AutomaticWeightResolution {
        if (! $measuredData->isComplete()) {
            return null;
        }

        $groupingMode = SessionGroupingMode::tryFrom((string) $groupingMode)?->value ?? SessionGroupingMode::defaultMode();
        $groupSize = max(1, (int) ($groupSize ?? SessionGroupingMode::defaultGroupSize()));

        $strategyMap = SessionGroupBuilder::buildStrategyMap($weeks, $sessionCounts, $groupingMode, $groupSize);
        $target1RM = $this->calculateTargetOneRepMax($setting, $measuredData);
        $groupTargets = $this->calculateGroupTargets(
            $target1RM,
            $strategyMap['orderedSessions'],
            $setsPerWeek,
            $resolvedRepsForCell,
            $resolvedSetsForSession,
        );

        $weights = [];
        $oneRepMax = [];
        $weightSessionGrid = [];
        $oneRepMaxSessionGrid = [];
        $lastSession = $strategyMap['orderedSessions'] === []
            ? null
            : $strategyMap['orderedSessions'][array_key_last($strategyMap['orderedSessions'])];

        for ($week = 0; $week < $weeks; $week++) {
            $setCount = $setsPerWeek[$week];
            $baselineGroup = $strategyMap['groupIndexByWeekSession'][$week][0] ?? $week;
            $setWeights = $this->calculateSetWeights($groupTargets[$baselineGroup] ?? 0, $setCount);

            $weights[$week] = [];
            $oneRepMax[$week] = [];

            for ($set = 0; $set < $setCount; $set++) {
                $weights[$week][$set] = self::roundWeight($setWeights[$set]);
                $oneRepMax[$week][$set] = '-';
            }
        }

        foreach ($strategyMap['orderedSessions'] as $session) {
            $week = $session['week'];
            $sessionIndex = $session['session'];
            $setCount = $resolvedSetsForSession !== null
                ? max(0, (int) $resolvedSetsForSession($week, $sessionIndex, (int) ($setsPerWeek[$week] ?? 0)))
                : (int) ($setsPerWeek[$week] ?? 0);
            $setWeights = $this->calculateSetWeights($groupTargets[$session['group']] ?? 0, $setCount);
            $lastSetIndex = $setCount - 1;

            for ($set = 0; $set < $setCount; $set++) {
                $weightSessionGrid[$week][$sessionIndex][$set] = self::roundWeight($setWeights[$set]);
                $oneRepMaxSessionGrid[$week][$sessionIndex][$set] = (
                    $lastSession !== null
                    && $week === $lastSession['week']
                    && $sessionIndex === $lastSession['session']
                    && $set === $lastSetIndex
                )
                    ? round($target1RM, 1)
                    : '-';
            }
        }

        return new AutomaticWeightResolution(
            weights: $weights,
            oneRepMax: $oneRepMax,
            summary: $this->buildSummary($setting, $measuredData, $target1RM),
            weightSessionGrid: $weightSessionGrid,
            oneRepMaxSessionGrid: $oneRepMaxSessionGrid,
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
    private function calculateGroupTargets(
        float $target1RM,
        array $orderedSessions,
        array $setsPerWeek,
        callable $resolvedRepsForCell,
        ?callable $resolvedSetsForSession = null,
    ): array {
        $targets = [];
        $lastSession = $orderedSessions === []
            ? null
            : $orderedSessions[array_key_last($orderedSessions)];

        if ($lastSession === null) {
            return $targets;
        }

        $lastWeekIndex = $lastSession['week'];
        $lastSessionIndex = $lastSession['session'];
        $lastGroupIndex = $lastSession['group'];
        $lastSetIndex = $resolvedSetsForSession !== null
            ? max(0, (int) $resolvedSetsForSession($lastWeekIndex, $lastSessionIndex, (int) ($setsPerWeek[$lastWeekIndex] ?? 0)) - 1)
            : ((int) ($setsPerWeek[$lastWeekIndex] ?? 1) - 1);

        $lastSetReps = $resolvedRepsForCell($lastWeekIndex, $lastSetIndex, $lastSessionIndex);
        $totalReps = BilateralReps::parse($lastSetReps ?? 1)->total();
        $repPercentage = RepPercentageTable::getPercentage($totalReps);
        $anchorWeight = $target1RM * $repPercentage;

        $targets[$lastGroupIndex] = $anchorWeight;
        $currentWeight = $anchorWeight;

        for ($group = $lastGroupIndex - 1; $group >= 0; $group--) {
            $currentWeight = self::decrementWeight($currentWeight);
            $targets[$group] = $currentWeight;
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
