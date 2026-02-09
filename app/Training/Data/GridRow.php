<?php

namespace App\Training\Data;

class GridRow
{
    public function __construct(
        public string $field,
        public string $color,
        public string $overrideColor,
        public string $editableMode = 'always',
        public string $inputType = 'number',
        public string $inputStep = '1',
        public ?string $suffix = null,
    ) {}
}
