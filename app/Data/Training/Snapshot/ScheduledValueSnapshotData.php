<?php

namespace App\Data\Training\Snapshot;

use Coda\Cms\Data\AbstractData;

class ScheduledValueSnapshotData extends AbstractData
{
    public function __construct(
        public int $id,
        public string $settingKey,
        public mixed $plannedValue = null,
        public mixed $actualValue = null,
        public mixed $resolvedValue = null,
        public ?string $unit = null,
        public bool $isModified = false,
    ) {}
}
