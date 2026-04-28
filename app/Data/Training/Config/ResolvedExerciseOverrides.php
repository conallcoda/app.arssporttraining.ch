<?php

namespace App\Data\Training\Config;

final readonly class ResolvedExerciseOverrides
{
    public function __construct(
        public ExerciseOverrides $defaultOverrides,
        public ?ExerciseOverrides $userOverrides,
        public array $effectiveConfig,
        /** @var array{cells: array, weeks: array} */
        public array $overrideLayer,
        public ?string $effectiveStartsAtDate,
        public bool $disabled,
    ) {}
}
