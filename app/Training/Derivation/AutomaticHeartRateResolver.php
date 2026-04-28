<?php

namespace App\Training\Derivation;

use App\Data\Exercise\Settings\HeartRateSetting;
use App\Data\Exercise\Strategies\HeartRate\HeartRateZoneCellColors;
use App\Training\Reference\BikingZoneTable;
use App\Training\Reference\JoggingZoneTable;

class AutomaticHeartRateResolver
{
    /**
     * @param  array<int, int>  $setsPerWeek
     * @param  array<int, array{week:int,session:int,set:int}>  $sessionCoordinates
     */
    public function resolve(
        HeartRateSetting $setting,
        int $weeks,
        array $setsPerWeek,
        callable $resolvedZoneForCell,
        callable $isZoneOverridden,
        array $sessionCoordinates = [],
        int $maxHR = 193,
        int $iatPercent = 90,
    ): ?AutomaticStrategyResolution {
        $defaultRange = $this->resolveRange($setting->mode, '2', $maxHR, $iatPercent);

        if ($defaultRange === null) {
            return null;
        }

        $zoneCellColors = new HeartRateZoneCellColors;
        $heartRateGrid = [];
        $heartRateSessionGrid = [];
        $colorGrid = [];
        $overrideColorGrid = [];
        $sessionColorGrid = [];
        $sessionOverrideColorGrid = [];

        for ($week = 0; $week < $weeks; $week++) {
            $setCount = $setsPerWeek[$week];

            for ($set = 0; $set < $setCount; $set++) {
                $zone = $resolvedZoneForCell($week, $set, null) ?? '2';
                $heartRateGrid[$week][$set] = $this->resolveRange($setting->mode, (string) $zone, $maxHR, $iatPercent) ?? $defaultRange;

                $zoneOverridden = $isZoneOverridden($week, $set, null);
                $color = $zoneOverridden
                    ? $zoneCellColors->cellOverrideColor('heartRateZone', $zone)
                    : $zoneCellColors->cellColor('heartRateZone', $zone);

                if ($color !== null) {
                    $colorGrid[$week][$set] = $color;
                    $overrideColorGrid[$week][$set] = $color;
                }
            }
        }

        foreach ($sessionCoordinates as $coordinate) {
            $week = $coordinate['week'];
            $session = $coordinate['session'];
            $set = $coordinate['set'];
            $zone = $resolvedZoneForCell($week, $set, $session) ?? '2';

            $heartRateSessionGrid[$week][$session][$set] = $this->resolveRange($setting->mode, (string) $zone, $maxHR, $iatPercent) ?? $defaultRange;

            $zoneOverridden = $isZoneOverridden($week, $set, $session);
            $color = $zoneOverridden
                ? $zoneCellColors->cellOverrideColor('heartRateZone', $zone)
                : $zoneCellColors->cellColor('heartRateZone', $zone);

            if ($color !== null) {
                $sessionColorGrid[$week][$session][$set] = $color;
                $sessionOverrideColorGrid[$week][$session][$set] = $color;
            }
        }

        return new AutomaticStrategyResolution([
            'heartRate' => new ResolvedGridField(
                grid: $heartRateGrid,
                sessionGrid: $heartRateSessionGrid,
                cellColorGrid: $colorGrid,
                cellOverrideColorGrid: $overrideColorGrid,
                sessionCellColorGrid: $sessionColorGrid,
                sessionCellOverrideColorGrid: $sessionOverrideColorGrid,
            ),
        ]);
    }

    public function resolveRange(string $mode, string $zone, int $maxHR, int $iatPercent): ?string
    {
        $tableClass = $this->resolveTableClass($mode);

        if ($tableClass === null) {
            return null;
        }

        return $tableClass::getRangeForZoneSpec($zone, $maxHR, $iatPercent);
    }

    /** @return class-string<BikingZoneTable|JoggingZoneTable>|null */
    private function resolveTableClass(string $mode): ?string
    {
        return match ($mode) {
            'automatic_biking' => BikingZoneTable::class,
            'automatic_jogging' => JoggingZoneTable::class,
            default => null,
        };
    }
}
