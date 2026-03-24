<?php

namespace App\Data\Exercise\Strategies\Contracts;

interface DefinesCellColors
{
    public function cellColor(string $field, mixed $value): ?string;
}
