<?php

namespace App\Data\Training\Planned;

final readonly class ResolvedPlannedSet
{
    /**
     * @param  ResolvedPlannedValue[]  $values
     */
    public function __construct(
        public int $setNumber,
        public array $values,
    ) {}
}
