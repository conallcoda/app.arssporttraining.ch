<?php

namespace App\Models\Metrics\Contracts;

interface HasMetricTypes
{
    public static function getAllowedMetricTypes(): array;
}
