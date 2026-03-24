<?php

namespace App\Data\Exercise\Preview;

use Coda\Cms\Data\AbstractData;

class PreviewGridRow extends AbstractData
{
    public function __construct(
        public string $field,
        public string $label,
        public string $color,
        /** @var array<int, array<int, string|int|float>>|array<int, string|int|float> */
        public array $cells = [],
        public string $overrideColor = '',
        /** @var array<int, array<int, bool>>|array<int, bool> */
        public array $overrides = [],
        public ?CellInputMeta $inputMeta = null,
        /** @var array<int, array<int, bool>>|array<int, bool> */
        public array $editableMap = [],
        public bool $lastSessionOnly = false,
        /** @var array<int, array<int, string>>|array<int, string> */
        public array $cellColorMap = [],
        /** @var array<int, array<int, string>>|array<int, string> */
        public array $cellOverrideColorMap = [],
    ) {}

    public function isCellEditable(int $week, ?int $set = null): bool
    {
        if ($set !== null) {
            return $this->editableMap[$week][$set] ?? true;
        }

        return $this->editableMap[$week] ?? true;
    }

    public function resolveCellColor(int $week, ?int $set = null, bool $overridden = false): string
    {
        if ($overridden) {
            $overrideColor = $set !== null
                ? ($this->cellOverrideColorMap[$week][$set] ?? null)
                : ($this->cellOverrideColorMap[$week] ?? null);

            if ($overrideColor !== null) {
                return $overrideColor;
            }

            return $this->overrideColor;
        }

        $cellColor = $set !== null
            ? ($this->cellColorMap[$week][$set] ?? null)
            : ($this->cellColorMap[$week] ?? null);

        return $cellColor ?? $this->color;
    }
}
