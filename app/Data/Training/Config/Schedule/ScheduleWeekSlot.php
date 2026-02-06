<?php

namespace App\Data\Training\Config\Schedule;

use App\Cms\Data\AbstractConfig;
use Spatie\LaravelData\Optional;

class ScheduleWeekSlot extends AbstractConfig
{
    public function __construct(
        public int $day,
        public int $slot,
        public ?int $programId,
        public string|Optional $linkedTo,
        public array|Optional $moved,
        public bool|Optional $isLinked,
    ) {}
}
