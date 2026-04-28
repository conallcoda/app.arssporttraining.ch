<?php

namespace App\Data\Exercise\Strategies\HeartRate;

use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Settings\HeartRateSetting;
use App\Data\Exercise\Strategies\Contracts\DefinesEditability;
use App\Training\Reference\BikingZoneTable;
use App\Training\Reference\JoggingZoneTable;

class NorwegianIntensityStrategy implements DefinesEditability
{
    public function __construct(
        private HeartRateSetting $setting,
        private int $maxHR = 193,
        private int $iatPercent = 90,
    ) {}

    public function isEditable(string $field, int $week, int $set, GridState $state): bool
    {
        return $field !== 'heartRate';
    }

    /** @return array<int, array<int, string>>|null */
    public function generate(int $weeks, GridState $state): ?array
    {
        $tableClass = $this->resolveTableClass();

        if ($tableClass === null) {
            return null;
        }

        $zoneCellColors = new HeartRateZoneCellColors;
        $setsPerWeek = $state->getSetsPerWeek();
        $heartRateGrid = [];
        $heartRateSessionGrid = [];
        $colorGrid = [];
        $overrideColorGrid = [];
        $sessionColorGrid = [];
        $sessionOverrideColorGrid = [];

        for ($week = 0; $week < $weeks; $week++) {
            $setCount = $setsPerWeek[$week];

            for ($set = 0; $set < $setCount; $set++) {
                $zone = $state->getResolvedCellValue('heartRateZone', $week, $set) ?? '2';
                $heartRateGrid[$week][$set] = $tableClass::getRangeForZoneSpec(
                    (string) $zone,
                    $this->maxHR,
                    $this->iatPercent,
                );

                $zoneOverridden = $state->isCellOverridden('heartRateZone', $week, $set);
                $color = $zoneOverridden
                    ? $zoneCellColors->cellOverrideColor('heartRateZone', $zone)
                    : $zoneCellColors->cellColor('heartRateZone', $zone);

                if ($color !== null) {
                    $colorGrid[$week][$set] = $color;
                    $overrideColorGrid[$week][$set] = $color;
                }
            }
        }

        $state->setGrid('heartRate', $heartRateGrid);
        foreach (($state->getOverrides()?->cells ?? []) as $override) {
            if (! isset($override->data['heartRateZone'])) {
                continue;
            }

            $week = $override->week;
            $session = $override->session;
            $set = $override->set;
            $zone = $state->getResolvedCellValue('heartRateZone', $week, $set, $session) ?? '2';

            $heartRateSessionGrid[$week][$session][$set] = $tableClass::getRangeForZoneSpec(
                (string) $zone,
                $this->maxHR,
                $this->iatPercent,
            );

            $zoneOverridden = $state->isCellOverridden('heartRateZone', $week, $set, $session);
            $color = $zoneOverridden
                ? $zoneCellColors->cellOverrideColor('heartRateZone', $zone)
                : $zoneCellColors->cellColor('heartRateZone', $zone);

            if ($color !== null) {
                $sessionColorGrid[$week][$session][$set] = $color;
                $sessionOverrideColorGrid[$week][$session][$set] = $color;
            }
        }

        $state->setSessionGrid('heartRate', $heartRateSessionGrid);
        $state->setCellColorGrid('heartRate', $colorGrid);
        $state->setCellOverrideColorGrid('heartRate', $overrideColorGrid);
        $state->setSessionCellColorGrid('heartRate', $sessionColorGrid);
        $state->setSessionCellOverrideColorGrid('heartRate', $sessionOverrideColorGrid);

        return $heartRateGrid;
    }

    /** @return class-string<BikingZoneTable|JoggingZoneTable>|null */
    private function resolveTableClass(): ?string
    {
        return match ($this->setting->mode) {
            'automatic_biking' => BikingZoneTable::class,
            'automatic_jogging' => JoggingZoneTable::class,
            default => null,
        };
    }
}
