<?php

namespace App\Livewire\Test\Data\Preview;

class GridState
{
    /** @var array<int, int> */
    private array $setsPerWeek = [];

    /** @var array<string, array<int, array<int, mixed>>> */
    private array $grids = [];

    /** @var array<string, array<string, mixed>> */
    private array $metadata = [];

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
}
