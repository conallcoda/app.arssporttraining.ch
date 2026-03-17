<?php

namespace App\Data\Athlete\Metric;

enum MetricEnum: string
{
    case OneRepMax = 'oneRepMax';
    case TargetOneRepMax = 'targetOneRepMax';

    public function label(): string
    {
        return match ($this) {
            self::OneRepMax => '1RM',
            self::TargetOneRepMax => 'Target 1RM',
        };
    }

    /** @return class-string<AbstractMetric> */
    public function metricClass(): string
    {
        return match ($this) {
            self::OneRepMax => Metrics\OneRepMaxMetric::class,
            self::TargetOneRepMax => Metrics\TargetOneRepMaxMetric::class,
        };
    }

    /** @return array<string, class-string<AbstractMetric>> */
    public static function metricMap(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->metricClass()])
            ->all();
    }
}
