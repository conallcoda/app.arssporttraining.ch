<?php

namespace App\Data\Training\Audit;

final readonly class ScheduledSnapshotAuditResultData
{
    /**
     * @param  ScheduledSnapshotMismatchData[]  $mismatches
     */
    public function __construct(
        public int $slotId,
        public ScheduledSnapshotClassificationData $classification,
        public bool $matches,
        public int $mismatchCount,
        public array $mismatches,
    ) {}
}
