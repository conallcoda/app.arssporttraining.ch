<?php

namespace Coda\Cms\Data\Charts;

use Coda\Cms\Data\AbstractData;

class BarChartData extends AbstractData
{
    /**
     * @param  list<string>  $labels
     * @param  list<ChartDatasetData>  $datasets
     * @param  array{
     *    legendPosition?: string,
     *    valueSuffix?: string,
     *    valueDecimals?: int,
     *    suggestedMax?: int|float|null,
     *    indexAxis?: string,
     *    stacked?: bool,
     *    barBorderRadius?: int,
     *    barBorderWidth?: int,
     *    xTickMinRotation?: int,
     *    xTickMaxRotation?: int,
     *    yTickStepSize?: int|float|null,
     *    legendSwatches?: array<int, array{fillStyle?: string, strokeStyle?: string}>,
     *    tooltipTitles?: list<string>,
     *    tooltipMetrics?: list<array{
     *      title: string,
     *      supplyCount: int,
     *      demandCount: int,
     *      supplyPercent: float,
     *      demandPercent: float
     *    }>
     * }|array<string, mixed>  $options
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public array $labels,
        public array $datasets,
        public array $options = [],
    ) {}

    public function toChartDefinition(): array
    {
        return [
            'id' => $this->id,
            'type' => 'bar',
            'title' => $this->title,
            'description' => $this->description,
            'data' => [
                'labels' => $this->labels,
                'datasets' => array_map(
                    fn (ChartDatasetData $dataset): array => $dataset->toChartArray(),
                    $this->datasets,
                ),
            ],
            'options' => [
                'legendPosition' => 'bottom',
                'valueSuffix' => '',
                'valueDecimals' => 1,
                'suggestedMax' => null,
                'indexAxis' => 'x',
                'stacked' => false,
                'barBorderRadius' => 6,
                'barBorderWidth' => 1,
                'xTickMinRotation' => 0,
                'xTickMaxRotation' => 0,
                'yTickStepSize' => null,
                'legendSwatches' => [],
                'tooltipTitles' => [],
                'tooltipMetrics' => [],
                ...$this->options,
            ],
        ];
    }
}
