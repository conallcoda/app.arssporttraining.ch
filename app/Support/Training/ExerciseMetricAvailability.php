<?php

namespace App\Support\Training;

use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;

class ExerciseMetricAvailability
{
    public function missingRequiredMetrics(
        array $effectiveConfig,
        ?WeightProgressionSetting $weightProgression = null,
        ?int $maxHR = null,
        ?int $iatPercent = null,
    ): bool {
        if ($this->requiresAutomaticWeight($effectiveConfig) && ! $weightProgression?->isComplete()) {
            return true;
        }

        if ($this->requiresAutomaticHeartRate($effectiveConfig) && ! $this->hasCompleteHeartRateMetric($maxHR, $iatPercent)) {
            return true;
        }

        return false;
    }

    public function requiresAutomaticWeight(array $effectiveConfig): bool
    {
        return in_array(ExerciseSetting::Weight->value, $effectiveConfig['settings'] ?? [], true)
            && ($effectiveConfig['weight']['mode'] ?? 'manual') === 'automatic';
    }

    public function requiresAutomaticHeartRate(array $effectiveConfig): bool
    {
        return in_array(ExerciseSetting::HeartRate->value, $effectiveConfig['settings'] ?? [], true)
            && in_array(($effectiveConfig['heartRate']['mode'] ?? 'manual'), ['automatic_biking', 'automatic_jogging'], true);
    }

    private function hasCompleteHeartRateMetric(?int $maxHR, ?int $iatPercent): bool
    {
        return $maxHR !== null
            && $maxHR > 0
            && $iatPercent !== null;
    }
}
