<?php

namespace App\Data\Exercise\Strategies\Weight;

use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Exercise\Settings\WeightSetting;
use App\Data\Exercise\Strategies\Contracts\DefinesEditability;
use App\Training\Derivation\AutomaticWeightResolver;

class OneRepMaxFixedStrategy implements DefinesEditability
{
    public function __construct(
        private WeightSetting $setting,
        private WeightProgressionSetting $measuredData,
        private ?AutomaticWeightResolver $resolver = null,
    ) {}

    public function isEditable(string $field, int $week, int $set, GridState $state): bool
    {
        return $field !== 'oneRepMax';
    }

    /**
     * @return array{weights: array<int, array<int, float>>, oneRepMax: array<int, array<int, string|float>>}|null
     */
    public function generate(int $weeks, GridState $state): ?array
    {
        $resolver = $this->resolver ?? new AutomaticWeightResolver;
        $resolution = $resolver->resolve(
            $this->setting,
            $this->measuredData,
            $weeks,
            $state->getSetsPerWeek(),
            fn (int $weekIndex, int $setIndex): mixed => $state->getResolvedCellValue('reps', $weekIndex, $setIndex),
        );

        if ($resolution === null) {
            return null;
        }

        $state->applyAutomaticStrategyResolution($resolution);

        $weights = $resolution->field('weight')?->grid ?? [];
        $oneRepMax = $resolution->field('oneRepMax')?->grid ?? [];

        return [
            'weights' => $weights,
            'oneRepMax' => $oneRepMax,
        ];
    }
}
