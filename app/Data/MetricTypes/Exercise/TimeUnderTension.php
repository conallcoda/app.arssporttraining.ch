<?php

namespace App\Data\MetricTypes\Exercise;

use App\Data\MetricTypes\AbstractMetricType;

class TimeUnderTension extends AbstractMetricType
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
        return ['regex:/^\d{4}$/'];
    }
}
