<?php

namespace Coda\Cms\Data\Charts;

use Coda\Cms\Data\AbstractData;

class PieChartData extends AbstractData
{
    /**
     * @param  list<string>  $labels
     * @param  list<ChartDatasetData>  $datasets
     * @param  array{
     *    legendPosition?: string,
     *    showSliceLabels?: bool,
     *    sliceLabelFormat?: string
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
            'type' => 'pie',
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
                'showSliceLabels' => false,
                'sliceLabelFormat' => 'percentage',
                ...$this->options,
            ],
        ];
    }
}
