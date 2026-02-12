<?php

namespace App\Livewire\Test\Data\Strategies\Sets;

use App\Livewire\Test\Data\Preview\GridState;
use App\Livewire\Test\Data\Settings\SetsSetting;

class DeloadSetsStrategy
{
    public function __construct(private SetsSetting $setting) {}

    /** @return array<int, int> */
    public function generate(int $weeks, GridState $state): array
    {
        $setsPerWeek = [];

        for ($week = 0; $week < $weeks; $week++) {
            $setsPerWeek[$week] = $this->getSetsForWeek($week);
        }

        $state->setSetsPerWeek($setsPerWeek);

        return $setsPerWeek;
    }

    private function getSetsForWeek(int $weekIndex): int
    {
        if ($this->setting->deload === 'none') {
            return $this->setting->default;
        }

        $weekNumber = $weekIndex + 1;

        $isDeloadWeek = match ($this->setting->deload) {
            'odd' => $weekNumber % 2 === 1,
            'even' => $weekNumber % 2 === 0,
            default => false,
        };

        if ($isDeloadWeek) {
            return max(0, $this->setting->default - $this->setting->deloadBy);
        }

        return $this->setting->default;
    }
}
