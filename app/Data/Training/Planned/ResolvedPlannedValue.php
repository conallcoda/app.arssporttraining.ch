<?php

namespace App\Data\Training\Planned;

final readonly class ResolvedPlannedValue
{
    public function __construct(
        public string $settingKey,
        public mixed $value,
        public ?string $unit = null,
        public string $applyPer = 'session',
        public ?ResolvedPlannedProvenance $provenance = null,
    ) {}
}
