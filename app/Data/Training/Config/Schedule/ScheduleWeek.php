<?php

namespace App\Data\Training\Config\Schedule;

use App\Cms\Data\AbstractConfig;

/**
 * @property ScheduleWeekSlot[] $slots
 */
class ScheduleWeek extends AbstractConfig
{
    public function __construct(
        public string $id,
        public ?string $linkedTo = null,
        public int $sort,
        public array $slots = [],
    ) {}
}
