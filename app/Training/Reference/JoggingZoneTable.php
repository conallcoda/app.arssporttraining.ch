<?php

namespace App\Training\Reference;

class JoggingZoneTable
{
    /** @var array<int, array{0: int, 1: int|null}> zone => [lowerPercent, upperPercent] */
    protected static array $table = [
        0 => [55, 70],
        1 => [70, 80],
        2 => [85, null],
        3 => [95, 100],
        4 => [100, 105],
    ];

    /** @return array{lower: int, upper: int} */
    public static function getRange(int $zone, int $maxHR, int $iatPercent = 90): array
    {
        $zone = max(0, min(4, $zone));
        [$lowerPct, $upperPct] = self::$table[$zone];

        $upperPct ??= $iatPercent + 5;

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
