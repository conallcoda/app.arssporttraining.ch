<?php

namespace App\Training\Reference;

class BikingZoneTable
{
    /** @var array<int, array{0: int, 1: int|null}> zone => [lowerPercent, upperPercent] */
    protected static array $table = [
        0 => [50, 65],
        1 => [65, 75],
        2 => [80, null],
        3 => [90, 95],
        4 => [95, 100],
    ];

    /** @return array{lower: int, upper: int} */
    public static function getRange(int $zone, int $maxHR, int $iatPercent = 90): array
    {
        $zone = max(0, min(4, $zone));
        [$lowerPct, $upperPct] = self::$table[$zone];

        $upperPct ??= $iatPercent;

        $isLastZone = $zone === max(array_keys(self::$table));

        return [
            'lower' => (int) round($maxHR * $lowerPct / 100),
            'upper' => $isLastZone
                ? (int) round($maxHR * $upperPct / 100)
                : (int) round($maxHR * $upperPct / 100) - 1,
        ];
    }

    public static function getRangeForZoneSpec(string $zoneSpec, int $maxHR, int $iatPercent = 90): string
    {
        if (str_contains($zoneSpec, '-')) {
            [$startZone, $endZone] = array_map('intval', explode('-', $zoneSpec));
            $lower = self::getRange($startZone, $maxHR, $iatPercent)['lower'];
            $upper = self::getRange($endZone, $maxHR, $iatPercent)['upper'];

            return "{$lower}-{$upper}";
        }

        $range = self::getRange((int) $zoneSpec, $maxHR, $iatPercent);

        return "{$range['lower']}-{$range['upper']}";
    }

    /** @return array<int, array{0: int, 1: int|null}> */
    public static function getTable(): array
    {
        return self::$table;
    }
}
