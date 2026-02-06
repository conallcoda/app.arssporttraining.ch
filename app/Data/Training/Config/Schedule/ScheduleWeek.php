<?php

namespace App\Data\Training\Config\Schedule;

use App\Cms\Data\AbstractConfig;
use Spatie\LaravelData\Optional;

/**
 * @property ScheduleWeekSlot[] $slots
 */
class ScheduleWeek extends AbstractConfig
{
    public function __construct(
        public string $id,
        public string|null|Optional $linkedTo,
        public int|Optional $sort,
        public array $slots,
        public bool|Optional $removed,
    ) {}
}
