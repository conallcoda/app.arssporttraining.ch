<?php

namespace App\Data\Training\Compiled;

final readonly class CompiledTrainingExercise
{
    /**
     * @param  CompiledTrainingSet[]  $sets
     */
    public function __construct(
        public int $exerciseId,
        public int $sort,
        public ?string $group,
        public array $sets,
    ) {}
}
