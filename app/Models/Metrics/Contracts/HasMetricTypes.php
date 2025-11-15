<?php

namespace App\Models\Metrics\Contracts;

interface HasMetricTypes
{
    public static function getCustomMetricTypes(): bool|array;
    public static function getMetricTypes(): array;
    public function getMetrics(): array;
    public static function getMetricTypeModel(string $type): string;
}
