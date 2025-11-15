<?php

namespace App\Models\Metrics\Concerns;

use Illuminate\Support\Str;

trait InteractsWithMetrics
{
    public static function getCustomMetricTypes(): bool|array
    {
        return false;
    }

    public static function getMetricTypes(): array
    {
        return [];
    }

    public function getMetrics(): array
    {
        return [];
    }

    protected static function normalizeMetricTypes($types)
    {
        $mapScope = function ($modelClass) {
            $normalized = Str::snake(class_basename($modelClass));
            return [$normalized => $modelClass];
        };
        return collect($types)->mapWithKeys($mapScope)->toArray();
    }

    public static function getMetricTypeModel(string $type): string
    {
        $normalized = static::normalizeMetricTypes(static::getMetricTypes());

        if (!array_key_exists($type, $normalized)) {
            throw new \Exception("Invalid metric type: $type");
        }
        return $normalized[$type];
    }
}
