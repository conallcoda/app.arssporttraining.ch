<?php

namespace App\Data\MetricTypes\Generic;

class Percentage extends Number
{

    public static function defaults(): array
    {
        return [
            'step' => 5,
        ];
    }

    public static function unit($short = true): ?string
    {
        return $short ? '%' : 'percent';
    }
}
