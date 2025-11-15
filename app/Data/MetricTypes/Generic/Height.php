<?php

namespace App\Data\MetricTypes\Generic;

class Height extends Number
{

    public static function defaults(): array
    {
        return [
            'step' => 0.1,
        ];
    }

    public static function unit($short = true): ?string
    {
        return $short ? 'cm' : 'centimeters';
    }
}
