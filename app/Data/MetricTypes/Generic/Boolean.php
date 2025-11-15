<?php

namespace App\Data\MetricTypes\Generic;

use App\Data\MetricTypes\AbstractMetricType;

class Boolean extends AbstractMetricType
{

    public static function defaults(): array
    {
        return [];
    }

    public static function unit($short = true): ?string
    {
        return null;
    }

    public static function rules(): array
    {
        return ['boolean'];
    }
}
