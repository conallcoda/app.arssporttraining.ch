<?php

namespace App\Models\Training\ExercisePlan;

class FixedWeightStep
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

        if ($newWeight < 0) {
            return 0;
        }

        return $newWeight;
    }

    public static function step(float $weight, bool $increment = true): float
    {
        return $increment ? self::increment($weight) : self::decrement($weight);
    }
}
