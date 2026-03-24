<?php

namespace App\Data\Exercise\Strategies\HeartRate;

use App\Data\Exercise\Strategies\Contracts\DefinesCellColors;

class HeartRateZoneCellColors implements DefinesCellColors
{
    private const ZONE_COLORS = [
        '0' => 'bg-zinc-100 dark:bg-zinc-700/40',
        '1' => 'bg-green-100 dark:bg-green-800/40',
        '2' => 'bg-yellow-100 dark:bg-yellow-800/40',
        '3' => 'bg-red-100 dark:bg-red-800/40',
        '4' => 'bg-zinc-800 dark:bg-zinc-950 text-white',
    ];

    private const ZONE_OVERRIDE_COLORS = [
        '0' => 'bg-zinc-200 dark:bg-zinc-600/50',
        '1' => 'bg-green-200 dark:bg-green-700/50',
        '2' => 'bg-yellow-200 dark:bg-yellow-700/50',
        '3' => 'bg-red-200 dark:bg-red-700/50',
        '4' => 'bg-zinc-900 dark:bg-black text-white',
    ];

    public function cellColor(string $field, mixed $value): ?string
    {
        if ($field !== 'heartRateZone') {
            return null;
        }

        $zone = (string) $value;

        return self::ZONE_COLORS[$zone] ?? null;
    }

    public function cellOverrideColor(string $field, mixed $value): ?string
    {
        if ($field !== 'heartRateZone') {
            return null;
        }

        $zone = (string) $value;

        return self::ZONE_OVERRIDE_COLORS[$zone] ?? null;
    }
}
