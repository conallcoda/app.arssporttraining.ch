<?php

namespace App\Data\Exercise\Preview;

use App\Data\Training\Planned\ResolvedPlannedProvenance;
use Coda\Cms\Data\AbstractData;

class PreviewGridRow extends AbstractData
{
    public function __construct(
        public string $field,
        public string $label,
        public string $color,
        public ?string $clickField = null,
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
        /** @var array<int, array<int, array<int, string>>> */
        public array $sessionCellColorMap = [],
        /** @var array<int, array<int, array<int, string>>> */
        public array $sessionCellOverrideColorMap = [],
        /** @var array<int, array<int, ResolvedPlannedProvenance|null>>|array<int, ResolvedPlannedProvenance|null> */
        public array $provenanceMap = [],
        /** @var array<int, array<int, array<int, ResolvedPlannedProvenance|null>>> */
        public array $sessionProvenanceMap = [],
    ) {
        $this->clickField ??= $field;
    }

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

    public function getCellProvenance(int $week, int $set, ?int $session = null): ?ResolvedPlannedProvenance
    {
        if ($session !== null && array_key_exists($week, $this->sessionProvenanceMap)) {
            return $this->sessionProvenanceMap[$week][$session][$set] ?? null;
        }

        return $this->provenanceMap[$week][$set] ?? null;
    }

    public function resolveCellColor(int $week, ?int $set = null, bool $overridden = false, ?int $session = null): string
    {
        if ($overridden) {
            if ($session !== null) {
                $sessionOverrideColor = $set !== null
                    ? ($this->sessionCellOverrideColorMap[$week][$session][$set] ?? null)
                    : null;

                if ($sessionOverrideColor !== null) {
                    return $sessionOverrideColor;
                }
            }

            $overrideColor = $set !== null
                ? ($this->cellOverrideColorMap[$week][$set] ?? null)
                : ($this->cellOverrideColorMap[$week] ?? null);

            if ($overrideColor !== null) {
                return $overrideColor;
            }

            return $this->overrideColor;
        }

        if ($session !== null) {
            $sessionCellColor = $set !== null
                ? ($this->sessionCellColorMap[$week][$session][$set] ?? null)
                : null;

            if ($sessionCellColor !== null) {
                return $sessionCellColor;
            }
        }

        $cellColor = $set !== null
            ? ($this->cellColorMap[$week][$set] ?? null)
            : ($this->cellColorMap[$week] ?? null);

        return $cellColor ?? $this->color;
    }

    /**
     * @return array{value: string|int|float|null, overridden: bool, color: string, editable: bool, provenance: ?ResolvedPlannedProvenance}
     */
    public function presentCell(
        int $week,
        int $set,
        ?int $session = null,
        bool $editable = true,
        bool $locked = false,
        bool $visible = true,
    ): array {
        $value = $visible ? $this->getCellValue($week, $set, $session) : '-';
        $overridden = $visible ? $this->isCellOverriddenAt($week, $set, $session) : false;
        $provenance = $visible ? $this->getCellProvenance($week, $set, $session) : null;

        return [
            'value' => $value,
            'overridden' => $overridden,
            'color' => $this->resolveCellColor($week, $set, $overridden, $session),
            'editable' => $editable && ! $locked && $this->isCellEditable($week, $set) && $value !== '-',
            'provenance' => $provenance,
        ];
    }

    /**
     * @return array{value: string|int|float|null, overridden: bool, color: string, editable: bool, provenance: ?ResolvedPlannedProvenance}
     */
    public function presentWeekCell(
        int $week,
        ?int $session = null,
        bool $editable = true,
        bool $locked = false,
    ): array {
        $value = $this->getCellValue($week, 0, $session);
        $overridden = $this->isCellOverriddenAt($week, 0, $session);
        $provenance = $this->getCellProvenance($week, 0, $session);

        return [
            'value' => $value,
            'overridden' => $overridden,
            'color' => $this->resolveCellColor($week, null, $overridden, $session),
            'editable' => $editable && ! $locked && $this->isCellEditable($week) && $value !== '-',
            'provenance' => $provenance,
        ];
    }
}
