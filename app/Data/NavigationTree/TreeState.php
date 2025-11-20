<?php

namespace App\Data\NavigationTree;

use App\Data\AbstractData;

class TreeState extends AbstractData
{
    public function __construct(
        public ?string $selectedUuid = null,
        public array $expandedUuids = [],
    ) {}

    public function isExpanded(string $uuid): bool
    {
        return in_array($uuid, $this->expandedUuids);
    }

    public function toggleExpanded(string $uuid): void
    {
        $key = array_search($uuid, $this->expandedUuids);

        if ($key !== false) {
            unset($this->expandedUuids[$key]);
            $this->expandedUuids = array_values($this->expandedUuids);
        } else {
            $this->expandedUuids[] = $uuid;
        }
    }

    public function select(string $uuid): void
    {
        $this->selectedUuid = $uuid;
    }

    public function isSelected(string $uuid): bool
    {
        return $this->selectedUuid === $uuid;
    }
}
