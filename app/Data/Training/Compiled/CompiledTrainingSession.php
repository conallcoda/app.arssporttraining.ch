<?php

namespace App\Data\Training\Compiled;

final readonly class CompiledTrainingSession
{
    /**
     * @param  CompiledTrainingExercise[]  $exercises
     */
    public function __construct(
        public int $slotId,
        public string $scheduledDate,
        public string $compiledVersion,
        public array $exercises,
    ) {}
}
