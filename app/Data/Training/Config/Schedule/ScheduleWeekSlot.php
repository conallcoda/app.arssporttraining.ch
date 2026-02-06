<?php

namespace App\Data\Training\Config\Schedule;

use App\Cms\Data\AbstractConfig;

class ScheduleWeekSlot extends AbstractConfig
{
    public function __construct(
        public int $day,
        public int $slot,
        public ?int $programId = null,
        public ?string $linkedTo = null,
        public ?array $moved = null,
    ) {}
}
