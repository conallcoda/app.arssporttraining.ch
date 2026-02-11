<?php

namespace App\Livewire\Test\Data\Strategies\Sets;

class DeloadSetsStrategy
{
    public function __construct(
        private int $baseSets = 4,
        private string $deload = 'none',
        private int $deloadBy = 1,
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            baseSets: max(0, (int) ($config['default'] ?? 4)),
            deload: $config['deload'] ?? 'none',
            deloadBy: max(0, (int) ($config['deloadBy'] ?? 1)),
        );
    }

    /** @return array<int, int> */
    public function generate(int $weeks): array
    {
        $setsPerWeek = [];

        for ($week = 0; $week < $weeks; $week++) {
            $setsPerWeek[$week] = $this->getSetsForWeek($week);
        }

        return $setsPerWeek;
    }

    public function maxSets(int $weeks): int
    {
        return max($this->generate($weeks));
    }

    private function getSetsForWeek(int $weekIndex): int
    {
        if ($this->deload === 'none') {
            return $this->baseSets;
        }

        $weekNumber = $weekIndex + 1;

        $isDeloadWeek = match ($this->deload) {
            'odd' => $weekNumber % 2 === 1,
            'even' => $weekNumber % 2 === 0,
            default => false,
        };

        if ($isDeloadWeek) {
            return max(0, $this->baseSets - $this->deloadBy);
        }

        return $this->baseSets;
    }
}
