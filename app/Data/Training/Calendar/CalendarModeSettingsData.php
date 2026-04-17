<?php

namespace App\Data\Training\Calendar;

use Coda\Cms\Data\AbstractData;

class CalendarModeSettingsData extends AbstractData
{
    public function __construct(
        public ?string $start = null,
        public ?string $end = null,
        public ?string $preset = null,
    ) {}
}
