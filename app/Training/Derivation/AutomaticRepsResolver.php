<?php

namespace App\Training\Derivation;

use App\Data\Exercise\BilateralReps;
use App\Data\Exercise\Settings\RepsSetting;

class AutomaticRepsResolver
{
    public function resolve(RepsSetting $setting, int $weeks, array $setsPerWeek): AutomaticStrategyResolution
    {
        return new AutomaticStrategyResolution([
            'reps' => new ResolvedGridField(
                grid: $this->buildGrid($setting, $weeks, $setsPerWeek),
            ),
        ]);
    }

    /** @return array<int, array<int, string|int>> */
    public function buildGrid(RepsSetting $setting, int $weeks, array $setsPerWeek): array
    {
        $grid = [];

        for ($week = 0; $week < $weeks; $week++) {
            $grid[$week] = [];
            $totalSets = $setsPerWeek[$week];

            for ($set = 0; $set < $totalSets; $set++) {
                $reps = $this->resolveReps($setting, $week, $set, $totalSets);
                $grid[$week][$set] = $reps->isBilateral() ? (string) $reps : $reps->total();
            }
        }

        return $grid;
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
