<?php

namespace App\Training\Reference;

class RepPercentageTable
{
    protected static array $table = [
        1 => 1.00,
        2 => 0.96,
        3 => 0.94,
        4 => 0.91,
        5 => 0.88,
        6 => 0.86,
        7 => 0.83,
        8 => 0.80,
        9 => 0.77,
        10 => 0.74,
        11 => 0.71,
        12 => 0.67,
        13 => 0.65,
        14 => 0.63,
        15 => 0.62,
    ];

    protected static array $repBrackets = [
        ['min' => 0.82, 'max' => 0.87, 'reps' => 6],
        ['min' => 0.76, 'max' => 0.81, 'reps' => 8],
        ['min' => 0.70, 'max' => 0.75, 'reps' => 10],
        ['min' => 0.65, 'max' => 0.69, 'reps' => 12],
        ['min' => 0.60, 'max' => 0.64, 'reps' => 14],
    ];

    public static function getPercentage(int $reps): float
    {
        $reps = max(1, min(15, $reps));

        return self::$table[$reps];
    }

    public static function getReps(float $percentage): int
    {
        $closest = 1;
        $closestDiff = abs(self::$table[1] - $percentage);

        foreach (self::$table as $reps => $pct) {
            $diff = abs($pct - $percentage);
            if ($diff < $closestDiff) {
                $closestDiff = $diff;
                $closest = $reps;
            }
        }

        return $closest;
    }

    public static function getRepsRounded(float $percentage, int $roundTo = 2): int
    {
        foreach (self::$repBrackets as $bracket) {
            if ($percentage >= $bracket['min'] && $percentage <= $bracket['max']) {
                return $bracket['reps'];
            }
        }

        $reps = self::getReps($percentage);

        return (int) (round($reps / $roundTo) * $roundTo);
    }

    public static function getTable(): array
    {
        return self::$table;
    }
}
