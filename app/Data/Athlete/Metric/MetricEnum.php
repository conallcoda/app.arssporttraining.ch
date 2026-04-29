<?php

namespace App\Data\Athlete\Metric;

enum MetricEnum: string
{
    case OneRepMax = 'oneRepMax';
    case HeartRate = 'heartRate';
    case Readiness = 'readiness';

    public function label(): string
    {
        return match ($this) {
            self::OneRepMax => '1RM',
            self::HeartRate => 'Heart Rate',
            self::Readiness => 'Readiness',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::OneRepMax => '1RM',
            self::HeartRate => 'HR',
            self::Readiness => 'Readiness',
        };
    }

    /** @return class-string<AbstractMetric> */
    public function metricClass(): string
    {
        return match ($this) {
            self::OneRepMax => Metrics\OneRepMaxMetric::class,
            self::HeartRate => Metrics\HeartRateMetric::class,
            self::Readiness => Metrics\ReadinessMetric::class,
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
