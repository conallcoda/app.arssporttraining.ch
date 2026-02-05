<?php

namespace App\Data\Training\Config;

use App\Cms\Data\AbstractConfig;

class DefaultTrainingSchedule extends AbstractConfig
{
    public function __construct(
        public string $startDate = '',
        public int $duration = 5,
        public ?array $programsSelected = null,
    ) {}
}
