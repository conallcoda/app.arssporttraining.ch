<?php

namespace App\Data\Exercise\Strategies;

use App\Data\Exercise\Settings\HeartRateSetting;
use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Exercise\Settings\WeightSetting;
use App\Data\Exercise\Strategies\HeartRate\NorwegianIntensityStrategy;
use App\Data\Exercise\Strategies\Reps\PairedRepStrategy;
use App\Data\Exercise\Strategies\Weight\OneRepMaxFixedStrategy;
use App\Training\Derivation\AutomaticHeartRateResolver;
use App\Training\Derivation\AutomaticRepsResolver;
use App\Training\Derivation\AutomaticWeightResolver;

class AutomaticStrategyFactory
{
    public function __construct(
        private ?AutomaticRepsResolver $repsResolver = null,
        private ?AutomaticWeightResolver $weightResolver = null,
        private ?AutomaticHeartRateResolver $heartRateResolver = null,
    ) {}

    public function makeRepsStrategy(array $config): ?PairedRepStrategy
    {
        $setting = RepsSetting::from($config);

        if (($setting->mode ?? 'manual') !== 'automatic') {
            return null;
        }

        return new PairedRepStrategy(
            $setting,
            $this->repsResolver ?? new AutomaticRepsResolver,
        );
    }

    public function makeWeightStrategy(array $config, ?WeightProgressionSetting $measuredData): ?OneRepMaxFixedStrategy
    {
        if ($measuredData === null) {
            return null;
        }

        $setting = WeightSetting::from($config);

        if (($setting->mode ?? 'manual') !== 'automatic') {
            return null;
        }

        return new OneRepMaxFixedStrategy(
            $setting,
            $measuredData,
            $this->weightResolver ?? new AutomaticWeightResolver,
        );
    }

    public function makeHeartRateStrategy(array $config, ?int $maxHR, ?int $iatPercent): ?NorwegianIntensityStrategy
    {
        $setting = HeartRateSetting::from($config);

        if (($setting->mode ?? 'manual') === 'manual') {
            return null;
        }

        return new NorwegianIntensityStrategy(
            $setting,
            maxHR: $maxHR ?? 193,
            iatPercent: $iatPercent ?? 90,
            resolver: $this->heartRateResolver ?? new AutomaticHeartRateResolver,
        );
    }
}
