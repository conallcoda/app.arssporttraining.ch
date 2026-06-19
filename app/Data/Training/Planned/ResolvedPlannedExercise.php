<?php

namespace App\Data\Training\Planned;

final readonly class ResolvedPlannedExercise
{
    /**
     * @param  ResolvedPlannedSet[]  $sets
     */
    public function __construct(
        public ?int $exerciseId,
        public int $sort,
        public ?string $group,
        public string $type,
        public array $sets,
        public ?ResolvedPlannedProvenance $setCountProvenance = null,
        public ?int $programExerciseId = null,
        public array $effectiveConfig = [],
    ) {}
}
