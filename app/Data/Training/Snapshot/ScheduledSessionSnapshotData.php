<?php

namespace App\Data\Training\Snapshot;

use Coda\Cms\Data\AbstractData;

class ScheduledSessionSnapshotData extends AbstractData
{
    /**
     * @param  ScheduledExerciseSnapshotData[]  $exercises
     */
    public function __construct(
        public int $slotId,
        public string $scheduledDate,
        public array $exercises = [],
    ) {}
}
