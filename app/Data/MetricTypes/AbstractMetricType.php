<?php

namespace App\Data\MetricTypes;

use App\Data\AbstractData;

abstract class AbstractMetricType extends AbstractData implements MetricType
{

    public static function createFields(): array
    {
        return [];
    }

    public static function recordFields(): array
    {
        return [];
    }
}
