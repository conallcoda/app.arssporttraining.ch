<?php

namespace App\Data\Exercise\Strategies\Reps;

use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Exercise\Settings\RepsSetting;
use App\Training\Derivation\AutomaticRepsResolver;

class PairedRepStrategy
{
    public function __construct(
        private RepsSetting $setting,
        private ?AutomaticRepsResolver $resolver = null,
    ) {}

    /**
     * @return array<int, array<int, string|int>>
     */
    public function generate(
        int $weeks,
        GridState $state,
        array $sessionCounts = [],
        string $groupingMode = SessionGroupingMode::Week->value,
        int $groupSize = 4,
    ): array
    {
        $resolution = ($this->resolver ?? new AutomaticRepsResolver)->resolve(
            $this->setting,
            $weeks,
            $state->getSetsPerWeek(),
            $sessionCounts,
            $groupingMode,
            $groupSize,
            fn (int $week, int $session, int $default): int => (int) $state->getResolvedSessionValue('sets', $week, $session, $default),
        );
        $state->applyAutomaticStrategyResolution($resolution);
        $grid = $resolution->field('reps')?->grid ?? [];

        return $grid;
    }
}
