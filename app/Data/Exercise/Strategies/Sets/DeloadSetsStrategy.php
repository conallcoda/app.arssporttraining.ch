<?php

namespace App\Data\Exercise\Strategies\Sets;

use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Exercise\Strategies\Contracts\DefinesEditability;

class DeloadSetsStrategy implements DefinesEditability
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

    public function isEditable(string $field, int $week, int $set, GridState $state): bool
    {
        return $set < ($state->getSetsPerWeek()[$week] ?? 0);
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
