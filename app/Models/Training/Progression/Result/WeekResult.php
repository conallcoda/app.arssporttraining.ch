<?php

namespace App\Models\Training\Progression\Result;

use App\Data\AbstractData;
use App\Models\Training\Progression\Reference\RepPercentageTable;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class WeekResult extends AbstractData
{
    public function __construct(
        public int $weekIndex,
        public ?float $anchorWeight,
        public ?int $anchorReps,
        public ?float $projected1RM = null,
        public ?float $reference1RM = null,
        #[DataCollectionOf(SessionResult::class)]
        public array $sessions = [],
    ) {}

    public function getDerived1RM(): float
    {
        $percentage = RepPercentageTable::getPercentage($this->anchorReps);

        return $this->anchorWeight / $percentage;
    }

    public function getSession(string $sessionUuid): ?SessionResult
    {
        foreach ($this->sessions as $session) {
            if ($session->sessionUuid === $sessionUuid) {
                return $session;
            }
        }

        return null;
    }

    public function sessionCount(): int
    {
        return count($this->sessions);
    }

    public function isFullyLocked(): bool
    {
        if (empty($this->sessions)) {
            return false;
        }

        foreach ($this->sessions as $session) {
            if (! $session->isFullyLocked()) {
                return false;
            }
        }

        return true;
    }

    public function hasOverrides(): bool
    {
        foreach ($this->sessions as $session) {
            if ($session->hasOverrides()) {
                return true;
            }
        }

        return false;
    }

    public function getTotalVolume(): float
    {
        $volume = 0;

        foreach ($this->sessions as $session) {
            $volume += $session->getTotalVolume();
        }

        return $volume;
    }

    public function getWeekLabel(): string
    {
        return 'Week '.($this->weekIndex + 1);
    }
}
