<?php

namespace App\Data\Training\Snapshot;

use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use Coda\Cms\Data\AbstractData;

class ScheduledSetSnapshotData extends AbstractData
{
    /**
     * @param  ScheduledValueSnapshotData[]  $values
     */
    public function __construct(
        public int $id,
        public int $setNumber,
        public TrainingProgramSlotSetStatusEnum $status,
        public bool $hasAnyModification = false,
        public array $values = [],
    ) {}
}
