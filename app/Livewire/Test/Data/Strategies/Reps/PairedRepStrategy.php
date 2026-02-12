<?php

namespace App\Livewire\Test\Data\Strategies\Reps;

use App\Livewire\Test\Data\Settings\RepsSetting;

class PairedRepStrategy
{
    public function __construct(private RepsSetting $setting) {}

    /**
     * @param  array<int, int>  $setsPerWeek
     * @return array<int, array<int, int>>
     */
    public function generate(int $weeks, array $setsPerWeek): array
    {
        $grid = [];

        for ($week = 0; $week < $weeks; $week++) {
            $grid[$week] = [];
            for ($set = 0; $set < $setsPerWeek[$week]; $set++) {
                $grid[$week][$set] = $this->calculateReps($week, $set);
            }
        }

        return $grid;
    }

    private function calculateReps(int $weekIndex, int $setIndex): int
    {
        $anchorReps = $this->anchorRepsForWeek($weekIndex);
        $topTierReps = $anchorReps + $this->setting->decrement;

        return max(
            $topTierReps - (intdiv($setIndex, 2) * $this->setting->decrement),
            $this->setting->minimum,
        );
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
