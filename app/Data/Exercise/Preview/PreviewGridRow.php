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
        /** @var array<int, array<int, array<int, string|int|float>>> */
        public array $sessionCells = [],
        public string $overrideColor = '',
        /** @var array<int, array<int, bool>>|array<int, bool> */
        public array $overrides = [],
        /** @var array<int, array<int, array<int, bool>>> */
        public array $sessionOverrides = [],
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

    public function getCellValue(int $week, int $set, ?int $session = null): string|int|float|null
    {
        if ($session !== null && array_key_exists($week, $this->sessionCells)) {
            return $this->sessionCells[$week][$session][$set] ?? '-';
        }

        return $this->cells[$week][$set] ?? '-';
    }

    public function isCellOverriddenAt(int $week, int $set, ?int $session = null): bool
    {
        if ($session !== null && array_key_exists($week, $this->sessionOverrides)) {
            return $this->sessionOverrides[$week][$session][$set] ?? false;
        }

        return $this->overrides[$week][$set] ?? false;
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
