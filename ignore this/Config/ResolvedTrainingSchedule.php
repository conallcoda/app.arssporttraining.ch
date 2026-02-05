<?php

namespace App\Data\Training\Config;

use App\Cms\Data\AbstractConfig;

class ResolvedTrainingTarget extends AbstractConfig
{
    public function __construct(
        public string $startDate,
        public int $duration,
        public array $programsSelected,
    ) {}
}
