<?php

namespace App\Models\Training\ExercisePlan;

use App\Models\Training\Progression\Reference\RepPercentageTable;

class OneRepMaxConverter
{
    public static function getOneRepMax(int $reps, float $weight): float
    {
        $percentage = RepPercentageTable::getPercentage($reps);

        return $weight / $percentage;
    }

    public static function getWeight(int $reps, float $oneRepMax): float
    {
        $percentage = RepPercentageTable::getPercentage($reps);

        return $oneRepMax * $percentage;
    }

    public static function getReps(float $weight, float $oneRepMax): int
    {
        if ($oneRepMax <= 0) {
            return 1;
        }

        $percentage = $weight / $oneRepMax;

        return RepPercentageTable::getReps($percentage);
    }

    public static function getRepsRounded(float $weight, float $oneRepMax, int $roundTo = 2): int
    {
        if ($oneRepMax <= 0) {
            return 2;
        }

        $percentage = $weight / $oneRepMax;

        return RepPercentageTable::getRepsRounded($percentage, $roundTo);
    }
}
