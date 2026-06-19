<?php

namespace App\Data\Training\Compiler;

use App\Data\Training\Config\ExerciseOverrides;

final readonly class AuthoringExerciseData
{
    /**
     * @param  array<string, mixed>  $effectiveConfig
     * @param  array{sessions?: array<int, mixed>, cells?: array<int, mixed>}  $overrideLayer
     * @param  array<string, mixed>  $baseConfig
     */
    public function __construct(
        public ?int $exerciseId,
        public int $sort,
        public ?string $group,
        public string $type,
        public array $effectiveConfig,
        public array $overrideLayer = ['sessions' => [], 'cells' => []],
        public array $baseConfig = [],
        public ?ExerciseOverrides $defaultOverrides = null,
        public ?ExerciseOverrides $userOverrides = null,
        public bool $disabled = false,
        public ?int $programExerciseId = null,
    ) {}
}
