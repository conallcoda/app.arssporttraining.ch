<?php

namespace App\Livewire\Test\Data\Strategies\Reps;

class PairedRepStrategy
{
    public function __construct(
        private int $startingReps = 10,
        private int $stepDownInterval = 2,
        private int $decrement = 2,
        private int $minimum = 1,
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            startingReps: (int) ($config['default'] ?? 10),
            stepDownInterval: max(1, (int) ($config['stepDownInterval'] ?? 2)),
            decrement: (int) ($config['decrement'] ?? 2),
            minimum: max(1, (int) ($config['minimum'] ?? 1)),
        );
    }

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
        $topTierReps = $anchorReps + $this->decrement;

        return max(
            $topTierReps - (intdiv($setIndex, 2) * $this->decrement),
            $this->minimum,
        );
    }

    private function anchorRepsForWeek(int $weekIndex): int
    {
        $baseReps = $this->startingReps - $this->decrement;
        $drops = intdiv($weekIndex, $this->stepDownInterval);

        return max(
            $baseReps - ($drops * $this->decrement),
            $this->minimum,
        );
    }
}
