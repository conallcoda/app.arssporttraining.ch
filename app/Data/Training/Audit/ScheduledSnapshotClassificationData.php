<?php

namespace App\Data\Training\Audit;

final readonly class ScheduledSnapshotClassificationData
{
    /**
     * @param  string[]  $reasons
     */
    public function __construct(
        public string $kind,
        public array $reasons = [],
    ) {}

    public function isLockedPast(): bool
    {
        return $this->kind === 'locked_past';
    }

    public function isFutureOpen(): bool
    {
        return $this->kind === 'future_open';
    }

    public function isAmbiguousBoundary(): bool
    {
        return $this->kind === 'ambiguous_boundary';
    }
}
