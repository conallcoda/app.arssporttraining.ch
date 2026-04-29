<?php

namespace App\Data\Exercise\Strategies\HeartRate;

use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Settings\HeartRateSetting;
use App\Data\Exercise\Strategies\Contracts\DefinesEditability;
use App\Training\Derivation\AutomaticHeartRateResolver;

class NorwegianIntensityStrategy implements DefinesEditability
{
    public function __construct(
        private HeartRateSetting $setting,
        private int $maxHR = 193,
        private int $iatPercent = 90,
        private ?AutomaticHeartRateResolver $resolver = null,
    ) {}

    public function isEditable(string $field, int $week, int $set, GridState $state): bool
    {
        return true;
    }

    /** @return array<int, array<int, string>>|null */
    public function generate(int $weeks, GridState $state): ?array
    {
        $resolver = $this->resolver ?? new AutomaticHeartRateResolver;
        $sessionCoordinates = [];
        foreach (($state->getOverrides()?->cells ?? []) as $override) {
            if ($override->session === null || ! isset($override->data['heartRateZone'])) {
                continue;
            }

            $sessionCoordinates[] = [
                'week' => $override->week,
                'session' => $override->session,
                'set' => $override->set,
            ];
        }

        $resolution = $resolver->resolve(
            $this->setting,
            $weeks,
            $state->getSetsPerWeek(),
            fn (int $week, int $set, ?int $session): mixed => $state->getResolvedCellValue('heartRateZone', $week, $set, $session),
            fn (int $week, int $set, ?int $session): bool => $state->isCellOverridden('heartRateZone', $week, $set, $session),
            $sessionCoordinates,
            $this->maxHR,
            $this->iatPercent,
        );

        if ($resolution === null) {
            return null;
        }

        $state->applyAutomaticStrategyResolution($resolution);

        return $resolution->field('heartRate')?->grid ?? [];
    }
}
