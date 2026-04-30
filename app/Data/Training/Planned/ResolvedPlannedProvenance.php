<?php

namespace App\Data\Training\Planned;

final readonly class ResolvedPlannedProvenance
{
    public function __construct(
        public string $kind,
        public string $layer,
    ) {}
}
