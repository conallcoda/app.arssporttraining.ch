<?php

namespace App\Data\Training\Compiler;

use App\Data\Exercise\Settings\WeightProgressionSetting;

final readonly class PlanningContextData
{
    /**
     * @param  array<int, int>  $weekSessionCounts
     */
    public function __construct(
        public string $scheduledDate,
        public int $weekIndex,
        public int $sessionIndex,
        public int $sessionsPerWeek = 1,
        public array $weekSessionCounts = [1],
        public ?WeightProgressionSetting $weightProgression = null,
        public ?int $maxHR = null,
        public ?int $iatPercent = null,
        public int $slotIndex = 0,
    ) {}

    public function resolvedWeekCount(): int
    {
        return max(1, count($this->weekSessionCounts), $this->weekIndex + 1);
    }

    /**
     * @return array<int, int>
     */
    public function resolvedSessionCounts(int $weeks): array
    {
        $sessionCounts = $this->weekSessionCounts;

        if ($sessionCounts === []) {
            $sessionCounts = array_fill(0, $weeks, max(1, $this->sessionsPerWeek));
        }

        for ($index = count($sessionCounts); $index < $weeks; $index++) {
            $sessionCounts[$index] = 1;
        }

        $sessionCounts[$this->weekIndex] = max(1, $this->sessionsPerWeek);

        return $sessionCounts;
    }
}
