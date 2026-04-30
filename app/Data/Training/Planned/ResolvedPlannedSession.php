<?php

namespace App\Data\Training\Planned;

final readonly class ResolvedPlannedSession
{
    /**
     * @param  ResolvedPlannedExercise[]  $exercises
     */
    public function __construct(
        public int $weekIndex,
        public int $sessionIndex,
        public string $scheduledDate,
        public array $exercises,
    ) {}
}
