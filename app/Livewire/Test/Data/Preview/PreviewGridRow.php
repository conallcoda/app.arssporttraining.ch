<?php

namespace App\Livewire\Test\Data\Preview;

class PreviewGridRow
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
    ) {}

    public function isCellEditable(int $week, ?int $set = null): bool
    {
        if ($set !== null) {
            return $this->editableMap[$week][$set] ?? true;
        }

        return $this->editableMap[$week] ?? true;
    }
}
