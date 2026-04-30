<?php

namespace App\Training\Derivation;

use App\Data\Exercise\BilateralReps;
use App\Data\Exercise\Preview\SessionGroupBuilder;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Exercise\Settings\RepsSetting;

class AutomaticRepsResolver
{
    public function resolve(
        RepsSetting $setting,
        int $weeks,
        array $setsPerWeek,
        array $sessionCounts = [],
        string $groupingMode = SessionGroupingMode::Week->value,
        int $groupSize = 4,
        ?callable $resolvedSetsForSession = null,
    ): AutomaticStrategyResolution
    {
        $strategyMap = SessionGroupBuilder::buildStrategyMap($weeks, $sessionCounts, $groupingMode, $groupSize);

        return new AutomaticStrategyResolution([
            'reps' => new ResolvedGridField(
                grid: $this->buildGrid($setting, $weeks, $setsPerWeek, $strategyMap['groupIndexByWeekSession']),
                sessionGrid: $this->buildSessionGrid(
                    $setting,
                    $weeks,
                    $setsPerWeek,
                    $strategyMap['orderedSessions'],
                    $resolvedSetsForSession,
                ),
            ),
        ]);
    }

    /** @return array<int, array<int, string|int>> */
    public function buildGrid(RepsSetting $setting, int $weeks, array $setsPerWeek, array $groupIndexByWeekSession = []): array
    {
        $grid = [];

        for ($week = 0; $week < $weeks; $week++) {
            $grid[$week] = [];
            $totalSets = $setsPerWeek[$week];
            $progressionIndex = $groupIndexByWeekSession[$week][0] ?? $week;

            for ($set = 0; $set < $totalSets; $set++) {
                $reps = $this->resolveReps($setting, $progressionIndex, $set, $totalSets);
                $grid[$week][$set] = $reps->isBilateral() ? (string) $reps : $reps->total();
            }
        }

        return $grid;
    }

    /** @return array<int, array<int, array<int, string|int>>> */
    public function buildSessionGrid(
        RepsSetting $setting,
        int $weeks,
        array $setsPerWeek,
        array $orderedSessions = [],
        ?callable $resolvedSetsForSession = null,
    ): array {
        $sessionGrid = [];

        foreach ($orderedSessions as $session) {
            $week = $session['week'];
            $sessionIndex = $session['session'];
            $totalSets = $resolvedSetsForSession !== null
                ? max(0, (int) $resolvedSetsForSession($week, $sessionIndex, (int) ($setsPerWeek[$week] ?? 0)))
                : (int) ($setsPerWeek[$week] ?? 0);

            for ($set = 0; $set < $totalSets; $set++) {
                $reps = $this->resolveReps($setting, $session['group'], $set, $totalSets);
                $sessionGrid[$week][$sessionIndex][$set] = $reps->isBilateral() ? (string) $reps : $reps->total();
            }
        }

        return $sessionGrid;
    }

    public function resolveReps(RepsSetting $setting, int $weekIndex, int $setIndex, int $totalSets): BilateralReps
    {
        $anchorReps = $this->anchorRepsForWeek($setting, $weekIndex);
        $topTierReps = $anchorReps->increment($setting->decrement ?? 2);
        $midpoint = (int) ceil($totalSets / 2);

        $reps = $setIndex < $midpoint ? $topTierReps : $anchorReps;

        return $reps->clampMinimum($setting->minimum ?? 1);
    }

    private function anchorRepsForWeek(RepsSetting $setting, int $weekIndex): BilateralReps
    {
        $decrement = $setting->decrement ?? 2;
        $baseReps = BilateralReps::parse($setting->default ?? 10)
            ->decrement($decrement);
        $drops = intdiv($weekIndex, max(1, $setting->stepDownInterval ?? 2));

        return $baseReps->decrement($drops * $decrement)
            ->clampMinimum($setting->minimum ?? 1);
    }
}
