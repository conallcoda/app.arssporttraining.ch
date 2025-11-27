<?php

namespace App\Livewire\Concerns;

trait WithCompactLabels
{
    public bool $compact = false;

    protected array $labels = [
        'days' => [
            'full' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'compact' => ['M', 'T', 'W', 'T', 'F', 'S', 'S'],
        ],
        'daysShort' => [
            'full' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'compact' => ['M', 'T', 'W', 'T', 'F', 'S', 'S'],
        ],
        'slots' => [
            'full' => ['Morning', 'Afternoon'],
            'compact' => ['AM', 'PM'],
        ],
    ];

    public function getDayLabels(): array
    {
        return $this->labels['daysShort'][$this->compact ? 'compact' : 'full'];
    }

    public function getSlotLabels(): array
    {
        return $this->labels['slots'][$this->compact ? 'compact' : 'full'];
    }

    public function getWeekLabel(int $weekIndex): string
    {
        return $this->compact ? 'W' . ($weekIndex + 1) : 'Week ' . ($weekIndex + 1);
    }

    public function setCompact(bool $compact): void
    {
        $this->compact = $compact;
    }
}
