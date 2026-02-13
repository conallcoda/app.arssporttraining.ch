<?php

namespace App\Data\Training\Config\Exercise;

use App\Cms\Data\AbstractConfig;

/**
 * @property array{cells?: \App\Data\Training\Config\Cell\CellOverride[], weeks?: array<int, mixed>} $overrides
 */
class ExerciseOverride extends AbstractConfig
{
    public function __construct(
        public int $id,
        public ExerciseOverrideConfig $config,
        public array $overrides = [],
    ) {}
}
