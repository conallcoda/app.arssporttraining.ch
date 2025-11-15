<?php

namespace App\Data\MetricTypes\Generic;

class Distance extends Number
{

    public static function defaults(): array
    {
        return [
            'step' => 1,
        ];
    }

    public static function unit($short = true): ?string
    {
        return $short ? 'm' : 'meters';
    }
}
