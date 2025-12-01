<?php

namespace App\Models\Training\Progression\Reference;

class WeightBracket
{
    public static function getStepForWeight(float $weight): float
    {
        if ($weight > 100) {
            return 7.5;
        }

        if ($weight >= 50) {
            return 5.0;
        }

        return 2.5;
    }

    public static function roundToIncrement(float $weight, float $increment = 0.5): float
    {
        return round($weight / $increment) * $increment;
    }
}
