<?php

namespace App\Training\Derivation;

class ResolvedGridField
{
    /**
     * @param  array<int, array<int, mixed>>|null  $grid
     * @param  array<int, array<int, array<int, mixed>>>  $sessionGrid
     * @param  array<int, array<int, string>>  $cellColorGrid
     * @param  array<int, array<int, string>>  $cellOverrideColorGrid
     * @param  array<int, array<int, array<int, string>>>  $sessionCellColorGrid
     * @param  array<int, array<int, array<int, string>>>  $sessionCellOverrideColorGrid
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?array $grid = null,
        public array $sessionGrid = [],
        public array $cellColorGrid = [],
        public array $cellOverrideColorGrid = [],
        public array $sessionCellColorGrid = [],
        public array $sessionCellOverrideColorGrid = [],
        public array $metadata = [],
    ) {}
}
