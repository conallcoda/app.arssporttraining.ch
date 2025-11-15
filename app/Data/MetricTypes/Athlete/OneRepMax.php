<?php

namespace App\Data\MetricTypes\Athlete;

use App\Models\Metrics\Types\Weight;

class OneRepMax extends Weight
{
    public static function defaults(): array
    {
        return [
            'step' => 1,
        ];
    }
}
