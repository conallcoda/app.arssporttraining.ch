<?php

namespace App\Data\Training\Compiled;

final readonly class CompiledTrainingSet
{
    /**
     * @param  CompiledTrainingSetValue[]  $values
     */
    public function __construct(
        public int $setNumber,
        public array $values,
    ) {}
}
