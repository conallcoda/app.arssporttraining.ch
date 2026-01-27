<?php

namespace App\Training\Services;

class WeightStep
{
    public static function getStepForWeight(float $weight): float
    {
        if ($weight > 107.5) {
            return 7.5;
        }

        if ($weight >= 55) {
            return 5.0;
        }

        return 2.5;
    }

    public static function increment(float $weight): float
    {
        $step = self::getStepForWeight($weight);

        return $weight + $step;
    }

    public static function decrement(float $weight): float
    {
        $step = self::getStepForWeight($weight);
        $newWeight = $weight - $step;

        return max(0, $newWeight);
    }

    public static function round(float $weight, float $step = 0.5): float
    {
        return round($weight / $step) * $step;
    }
}
