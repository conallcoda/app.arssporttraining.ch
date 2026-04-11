<?php

namespace App\Data\Training\Compiled;

final readonly class CompiledTrainingSetValue
{
    public function __construct(
        public string $settingKey,
        public string $plannedValueType,
        public mixed $plannedValue,
        public ?string $unit = null,
    ) {}
}
