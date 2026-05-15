<?php

namespace App\Support\AthleteMetrics;

use App\Data\Exercise\Strategies\HeartRate\HeartRateZoneCellColors;
use App\Training\Reference\BikingZoneTable;
use App\Training\Reference\JoggingZoneTable;

class HeartRatePreviewBuilder
{
    /**
     * @return array<int, array{
     *     title: string,
     *     maxHeartRate: ?int,
     *     anaerobicThreshold: ?int,
     *     rows: array<int, array{name: string, bpm: string, percent: string, classes: string}>
     * }>
     */
    public function buildSections(?int $maxHeartRate, ?int $anaerobicThreshold): array
    {
        return [
            $this->buildSection(
                title: 'Bike',
                tableClass: BikingZoneTable::class,
                maxHeartRate: $maxHeartRate,
                anaerobicThreshold: $anaerobicThreshold,
                zoneTwoUpperPercent: $anaerobicThreshold,
            ),
            $this->buildSection(
                title: 'Jogging',
                tableClass: JoggingZoneTable::class,
                maxHeartRate: $maxHeartRate,
                anaerobicThreshold: $anaerobicThreshold,
                zoneTwoUpperPercent: $anaerobicThreshold !== null ? $anaerobicThreshold + 5 : null,
            ),
        ];
    }

    /**
     * @param  class-string<BikingZoneTable|JoggingZoneTable>  $tableClass
     * @return array{
     *     title: string,
     *     maxHeartRate: ?int,
     *     anaerobicThreshold: ?int,
     *     rows: array<int, array{name: string, bpm: string, percent: string, classes: string}>
     * }
     */
    protected function buildSection(
        string $title,
        string $tableClass,
        ?int $maxHeartRate,
        ?int $anaerobicThreshold,
        ?int $zoneTwoUpperPercent,
    ): array {
        $percentTable = $tableClass::getTable();
        $zoneRows = $this->zoneRows();
        $rows = [];

        foreach ($zoneRows as $zone => $meta) {
            [$lowerPercent, $upperPercent] = $percentTable[$zone];
            $range = ($maxHeartRate !== null && $anaerobicThreshold !== null)
                ? $tableClass::getRange($zone, $maxHeartRate, $anaerobicThreshold)
                : null;

            $rows[] = [
                'name' => $meta['name'],
                'bpm' => $range ? "{$range['lower']} - {$range['upper']} bpm" : '—',
                'percent' => match ($zone) {
                    2 => $zoneTwoUpperPercent !== null ? "{$lowerPercent}% - {$zoneTwoUpperPercent}%" : '—',
                    default => $upperPercent !== null ? "{$lowerPercent}% - {$upperPercent}%" : '—',
                },
                'classes' => $meta['classes'],
            ];
        }

        return [
            'title' => $title,
            'maxHeartRate' => $maxHeartRate,
            'anaerobicThreshold' => $anaerobicThreshold,
            'rows' => $rows,
        ];
    }

    /** @return array<int, array{name: string, classes: string}> */
    protected function zoneRows(): array
    {
        $zoneColors = new HeartRateZoneCellColors;

        return [
            0 => ['name' => 'Reg', 'classes' => $zoneColors->cellColor('heartRateZone', '0') ?? ''],
            1 => ['name' => 'Zone 1', 'classes' => $zoneColors->cellColor('heartRateZone', '1') ?? ''],
            2 => ['name' => 'Zone 2', 'classes' => $zoneColors->cellColor('heartRateZone', '2') ?? ''],
            3 => ['name' => 'Zone 3', 'classes' => $zoneColors->cellColor('heartRateZone', '3') ?? ''],
            4 => ['name' => 'Zone MAX', 'classes' => $zoneColors->cellColor('heartRateZone', '4') ?? ''],
        ];
    }
}
