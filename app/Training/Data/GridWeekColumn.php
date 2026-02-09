<?php

namespace App\Training\Data;

class GridWeekColumn
{
    public function __construct(
        public string $field,
        public string $label,
        public string $color,
        public string $overrideColor,
        public string $inputType = 'text',
        public ?string $inputStep = null,
        public ?string $suffix = null,
    ) {}
}
