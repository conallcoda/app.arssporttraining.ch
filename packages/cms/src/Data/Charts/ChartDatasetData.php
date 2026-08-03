<?php

namespace Coda\Cms\Data\Charts;

use Coda\Cms\Data\AbstractData;

class ChartDatasetData extends AbstractData
{
    /**
     * @param  list<int|float>  $values
     * @param  list<string>  $backgroundColor
     * @param  list<string>  $borderColor
     */
    public function __construct(
        public string $label,
        public array $values,
        public array $backgroundColor,
        public array $borderColor,
        public int $borderWidth = 1,
    ) {}

    /**
     * @return array{
     *   label: string,
     *   values: list<int|float>,
     *   backgroundColor: list<string>,
     *   borderColor: list<string>,
     *   borderWidth: int
     * }
     */
    public function toChartArray(): array
    {
        return [
            'label' => $this->label,
            'values' => $this->values,
            'backgroundColor' => $this->backgroundColor,
            'borderColor' => $this->borderColor,
            'borderWidth' => $this->borderWidth,
        ];
    }
}
