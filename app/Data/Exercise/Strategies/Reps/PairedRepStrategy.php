<?php

namespace App\Data\Exercise\Strategies\Reps;

use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Settings\RepsSetting;

class PairedRepStrategy
{
    public function __construct(private RepsSetting $setting) {}

    /**
     * @return array<int, array<int, int>>
     */
    public function generate(int $weeks, GridState $state): array
    {
        $setsPerWeek = $state->getSetsPerWeek();
        $grid = [];

        for ($week = 0; $week < $weeks; $week++) {
            $grid[$week] = [];
            $totalSets = $setsPerWeek[$week];
            for ($set = 0; $set < $totalSets; $set++) {
                $grid[$week][$set] = $this->calculateReps($week, $set, $totalSets);
            }
        }

        $state->setGrid('reps', $grid);

        return $grid;
    }

    private function calculateReps(int $weekIndex, int $setIndex, int $totalSets): int
    {
        $anchorReps = $this->anchorRepsForWeek($weekIndex);
        $topTierReps = $anchorReps + $this->setting->decrement;
        $midpoint = (int) ceil($totalSets / 2);

        $reps = $setIndex < $midpoint ? $topTierReps : $anchorReps;

        return max($reps, $this->setting->minimum);
    }

    private function anchorRepsForWeek(int $weekIndex): int
    {
        $baseReps = $this->setting->default - $this->setting->decrement;
        $drops = intdiv($weekIndex, max(1, $this->setting->stepDownInterval));

        return max(
            $baseReps - ($drops * $this->setting->decrement),
            $this->setting->minimum,
        );
    }
}
