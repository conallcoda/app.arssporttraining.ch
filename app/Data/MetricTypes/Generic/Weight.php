<?php

namespace App\Data\MetricTypes\Generic;

class Weight extends Number
{

    public static function defaults(): array
    {
        return [
            'step' => 1,
        ];
    }

    public static function unit($short = true): ?string
    {
        return $short ? 'kg' : 'kilograms';
    }
}
