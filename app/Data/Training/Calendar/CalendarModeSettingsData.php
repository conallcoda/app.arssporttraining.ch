<?php

namespace App\Data\Training\Calendar;

use Coda\Cms\Data\AbstractData;

class CalendarModeSettingsData extends AbstractData
{
    public function __construct(
        public string $period = 'month',
        public ?string $date = null,
        public ?string $start = null,
        public ?string $end = null,
    ) {}
}
