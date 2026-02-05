<?php

namespace App\Data\Training\Config;

use App\Cms\Data\AbstractConfig;

class AthleteTrainingSchedule extends AbstractConfig
{
    public function __construct(
        public ?string $startDate = null,
        public ?int $duration = null,
        public ?array $programsSelected = null,
    ) {}
}
