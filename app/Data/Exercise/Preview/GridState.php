<?php

namespace App\Data\Exercise\Preview;

use App\Data\Exercise\Strategies\Contracts\DefinesCellColors;
use App\Data\Exercise\Strategies\Contracts\DefinesEditability;
use App\Training\Derivation\AutomaticStrategyResolution;
use App\Training\Derivation\ResolvedGridField;

class GridState
{
    /** @var array<int, int> */
    private array $setsPerWeek = [];

    /** @var array<string, array<int, array<int, mixed>>> */
    private array $grids = [];

    /** @var array<string, array<int, array<int, array<int, mixed>>>> */
    private array $sessionGrids = [];

    /** @var array<string, array<string, mixed>> */
    private array $metadata = [];

    private ?GridOverrides $overrides = null;

    private ?GridOverrides $highlightOverrides = null;

    /** @var array<DefinesEditability> */
    private array $editabilityStrategies = [];

    /** @var array<DefinesCellColors> */
    private array $cellColorStrategies = [];

    /** @var array<string, array<int, array<int, string>>> */
    private array $cellColorGrids = [];

    /** @var array<string, array<int, array<int, string>>> */
    private array $cellOverrideColorGrids = [];

    /** @var array<string, array<int, array<int, array<int, string>>>> */
    private array $sessionCellColorGrids = [];

    /** @var array<string, array<int, array<int, array<int, string>>>> */
    private array $sessionCellOverrideColorGrids = [];

    /** @param array<int, int> $setsPerWeek */
    public function setSetsPerWeek(array $setsPerWeek): void
    {
        $this->setsPerWeek = $setsPerWeek;
    }

    /** @return array<int, int> */
    public function getSetsPerWeek(): array
    {
        return $this->setsPerWeek;
    }

    public function hasSetsPerWeek(): bool
    {
        return $this->setsPerWeek !== [];
    }

    public function maxSets(): int
    {
        if ($this->setsPerWeek === []) {
            return 0;
        }

        return max($this->setsPerWeek);
    }

    /** @param array<int, array<int, mixed>> $grid */
    public function setGrid(string $setting, array $grid): void
    {
        $this->grids[$setting] = $grid;
    }

    /** @return array<int, array<int, mixed>>|null */
    public function getGrid(string $setting): ?array
    {
        return $this->grids[$setting] ?? null;
    }

    public function hasGrid(string $setting): bool
    {
        return isset($this->grids[$setting]);
    }

    /** @param array<int, array<int, array<int, mixed>>> $grid */
    public function setSessionGrid(string $setting, array $grid): void
    {
        $this->sessionGrids[$setting] = $grid;
    }

    public function applyAutomaticStrategyResolution(AutomaticStrategyResolution $resolution): void
    {
        foreach ($resolution->fields as $setting => $field) {
            $this->applyResolvedGridField($setting, $field);
        }
    }

    public function applyResolvedGridField(string $setting, ResolvedGridField $field): void
    {
        if ($field->grid !== null) {
            $this->setGrid($setting, $field->grid);
        }

        if ($field->sessionGrid !== []) {
            $this->setSessionGrid($setting, $field->sessionGrid);
        }

        if ($field->cellColorGrid !== []) {
            $this->setCellColorGrid($setting, $field->cellColorGrid);
        }

        if ($field->cellOverrideColorGrid !== []) {
            $this->setCellOverrideColorGrid($setting, $field->cellOverrideColorGrid);
        }

        if ($field->sessionCellColorGrid !== []) {
            $this->setSessionCellColorGrid($setting, $field->sessionCellColorGrid);
        }

        if ($field->sessionCellOverrideColorGrid !== []) {
            $this->setSessionCellOverrideColorGrid($setting, $field->sessionCellOverrideColorGrid);
        }

        foreach ($field->metadata as $key => $value) {
            $this->setMetadata($setting, $key, $value);
        }
    }

    public function getCellValue(string $setting, int $weekIndex, int $setIndex): mixed
    {
        return $this->grids[$setting][$weekIndex][$setIndex] ?? null;
    }

    /** @return array<string, array<int, array<int, mixed>>> */
    public function getGrids(): array
    {
        return $this->grids;
    }

    public function setMetadata(string $setting, string $key, mixed $value): void
    {
        $this->metadata[$setting][$key] = $value;
    }

    public function getMetadata(string $setting, string $key): mixed
    {
        return $this->metadata[$setting][$key] ?? null;
    }

    public function setOverrides(GridOverrides $overrides): void
    {
        $this->overrides = $overrides;
    }

    public function getOverrides(): ?GridOverrides
    {
        return $this->overrides;
    }

    public function setHighlightOverrides(GridOverrides $highlightOverrides): void
    {
        $this->highlightOverrides = $highlightOverrides;
    }

    public function getResolvedCellValue(string $setting, int $weekIndex, int $setIndex, ?int $sessionIndex = null): mixed
    {
        if ($this->overrides !== null) {
            $overrideValue = $this->overrides->getCellOverrideValue($weekIndex, $setIndex, $setting, $sessionIndex);

            if ($overrideValue !== null) {
                return $overrideValue;
            }
        }

        if ($sessionIndex !== null) {
            $sessionValue = $this->sessionGrids[$setting][$weekIndex][$sessionIndex][$setIndex] ?? null;

            if ($sessionValue !== null) {
                return $sessionValue;
            }
        }

        return $this->getCellValue($setting, $weekIndex, $setIndex);
    }

    public function isCellOverridden(string $setting, int $weekIndex, int $setIndex, ?int $sessionIndex = null): bool
    {
        $overrides = $this->highlightOverrides ?? $this->overrides;

        if ($overrides === null) {
            return false;
        }

        return $overrides->hasCellOverride($weekIndex, $setIndex, $setting, $sessionIndex);
    }

    public function getResolvedWeekValue(string $setting, int $weekIndex, mixed $default): mixed
    {
        if ($this->overrides !== null) {
            $overrideValue = $this->overrides->getWeekOverrideValue($weekIndex, $setting);

            if ($overrideValue !== null) {
                return $overrideValue;
            }
        }

        return $default;
    }

    public function isWeekOverridden(string $setting, int $weekIndex): bool
    {
        $overrides = $this->highlightOverrides ?? $this->overrides;

        if ($overrides === null) {
            return false;
        }

        return $overrides->hasWeekOverride($weekIndex, $setting);
    }

    public function addEditabilityStrategy(DefinesEditability $strategy): void
    {
        $this->editabilityStrategies[] = $strategy;
    }

    public function isCellEditable(string $field, int $week, int $set): bool
    {
        foreach ($this->editabilityStrategies as $strategy) {
            if (! $strategy->isEditable($field, $week, $set, $this)) {
                return false;
            }
        }

        return true;
    }

    public function addCellColorStrategy(DefinesCellColors $strategy): void
    {
        $this->cellColorStrategies[] = $strategy;
    }

    /** @param array<int, array<int, string>> $colorGrid */
    public function setCellColorGrid(string $field, array $colorGrid): void
    {
        $this->cellColorGrids[$field] = $colorGrid;
    }

    /** @param array<int, array<int, string>> $colorGrid */
    public function setCellOverrideColorGrid(string $field, array $colorGrid): void
    {
        $this->cellOverrideColorGrids[$field] = $colorGrid;
    }

    /** @param array<int, array<int, array<int, string>>> $colorGrid */
    public function setSessionCellColorGrid(string $field, array $colorGrid): void
    {
        $this->sessionCellColorGrids[$field] = $colorGrid;
    }

    /** @param array<int, array<int, array<int, string>>> $colorGrid */
    public function setSessionCellOverrideColorGrid(string $field, array $colorGrid): void
    {
        $this->sessionCellOverrideColorGrids[$field] = $colorGrid;
    }

    public function getCellColor(string $field, int $week, int $set): ?string
    {
        return $this->cellColorGrids[$field][$week][$set] ?? null;
    }

    public function getCellOverrideColor(string $field, int $week, int $set): ?string
    {
        return $this->cellOverrideColorGrids[$field][$week][$set] ?? null;
    }

    public function getSessionCellColor(string $field, int $week, int $session, int $set): ?string
    {
        return $this->sessionCellColorGrids[$field][$week][$session][$set] ?? null;
    }

    public function getSessionCellOverrideColor(string $field, int $week, int $session, int $set): ?string
    {
        return $this->sessionCellOverrideColorGrids[$field][$week][$session][$set] ?? null;
    }

    public function getCellColorByValue(string $field, mixed $value): ?string
    {
        foreach ($this->cellColorStrategies as $strategy) {
            $color = $strategy->cellColor($field, $value);

            if ($color !== null) {
                return $color;
            }
        }

        return null;
    }

    public function getCellOverrideColorByValue(string $field, mixed $value): ?string
    {
        foreach ($this->cellColorStrategies as $strategy) {
            if (method_exists($strategy, 'cellOverrideColor')) {
                $color = $strategy->cellOverrideColor($field, $value);

                if ($color !== null) {
                    return $color;
                }
            }
        }

        return null;
    }
}
