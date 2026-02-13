<?php

namespace App\Data\Exercise\Preview;

use App\Cms\Data\AbstractData;

class CellInputMeta extends AbstractData
{
    public function __construct(
        public string $inputType = 'number',
        public string $inputStep = '1',
        public ?int $min = null,
        public ?int $max = null,
        public ?int $maxlength = null,
        public ?string $mask = null,
    ) {}
}
