<?php

namespace App\Data\Athlete\Metric;

enum MetricEnum: string
{
    case OneRepMax = 'oneRepMax';
    case HeartRate = 'heartRate';

    public function label(): string
    {
        return match ($this) {
            self::OneRepMax => '1RM',
            self::HeartRate => 'Heart Rate',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::OneRepMax => '1RM',
            self::HeartRate => 'HR',
        };
    }

    /** @return class-string<AbstractMetric> */
    public function metricClass(): string
    {
        return match ($this) {
            self::OneRepMax => Metrics\OneRepMaxMetric::class,
            self::HeartRate => Metrics\HeartRateMetric::class,
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
