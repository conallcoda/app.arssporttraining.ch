<?php

namespace App\Data\MetricTypes\Athlete;

use App\Data\MetricTypes\Generic\Weight;

class OneRepMax extends Weight
{
    public static function defaults(): array
    {
        return [
            'step' => 1,
        ];
    }

    public static function createFields(): array
    {
        return [];
    }
}
