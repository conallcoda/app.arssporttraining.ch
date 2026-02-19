<?php

namespace App\Data\Exercise\Strategies\Reps;

use App\Data\Exercise\BilateralReps;
use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Settings\RepsSetting;

class PairedRepStrategy
{
    public function __construct(private RepsSetting $setting) {}

    /**
     * @return array<int, array<int, string|int>>
     */
    public function generate(int $weeks, GridState $state): array
    {
        $setsPerWeek = $state->getSetsPerWeek();
        $grid = [];

        for ($week = 0; $week < $weeks; $week++) {
            $grid[$week] = [];
            $totalSets = $setsPerWeek[$week];
            for ($set = 0; $set < $totalSets; $set++) {
                $reps = $this->calculateReps($week, $set, $totalSets);
                $grid[$week][$set] = $reps->isBilateral() ? (string) $reps : $reps->total();
            }
        }

        $state->setGrid('reps', $grid);

        return $grid;
    }

    private function calculateReps(int $weekIndex, int $setIndex, int $totalSets): BilateralReps
    {
        $anchorReps = $this->anchorRepsForWeek($weekIndex);
        $topTierReps = $anchorReps->increment($this->setting->decrement);
        $midpoint = (int) ceil($totalSets / 2);

        $reps = $setIndex < $midpoint ? $topTierReps : $anchorReps;

        return $reps->clampMinimum($this->setting->minimum);
    }

    private function anchorRepsForWeek(int $weekIndex): BilateralReps
    {
        $baseReps = BilateralReps::parse($this->setting->default)
            ->decrement($this->setting->decrement);
        $drops = intdiv($weekIndex, max(1, $this->setting->stepDownInterval));

        return $baseReps->decrement($drops * $this->setting->decrement)
            ->clampMinimum($this->setting->minimum);
    }
}
