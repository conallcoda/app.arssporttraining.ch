<?php

namespace App\Data\Training\Config\Schedule;

use Coda\Cms\Data\AbstractConfig;

/**
 * @property ScheduleWeek[] $weeks
 */
class DefaultScheduleConfig extends AbstractConfig
{
    public function __construct(
        public string $startDate = '',
        public array $weeks = [],
    ) {}
}
