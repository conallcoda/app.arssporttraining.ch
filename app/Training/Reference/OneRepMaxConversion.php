<?php

namespace App\Training\Reference;

class OneRepMaxConversion
{
    public static function estimatedOneRepMax(int $reps, float $weight, float $modifier = 100): float
    {
        $percentage = RepPercentageTable::getPercentage($reps);
        $baseline = $weight / $percentage;

        return round($baseline * ($modifier / 100), 1);
    }

    public static function targetOneRepMax(float $starting1RM, int|float $goalPercent): float
    {
        return round($starting1RM * (1 + $goalPercent / 100), 1);
    }
}
