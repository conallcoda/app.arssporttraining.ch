<?php

namespace App\Data\Athlete\Metric;

enum MetricEnum: string
{
    case OneRepMax = 'oneRepMax';
    case HeartRateBiking = 'heartRateBiking';
    case HeartRateJogging = 'heartRateJogging';

    public function label(): string
    {
        return match ($this) {
            self::OneRepMax => '1RM',
            self::HeartRateBiking => 'HR Biking',
            self::HeartRateJogging => 'HR Jogging',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::OneRepMax => '1RM',
            self::HeartRateBiking => 'HRB',
            self::HeartRateJogging => 'HRJ',
        };
    }

    /** @return class-string<AbstractMetric> */
    public function metricClass(): string
    {
        return match ($this) {
            self::OneRepMax => Metrics\OneRepMaxMetric::class,
            self::HeartRateBiking => Metrics\HeartRateBikingMetric::class,
            self::HeartRateJogging => Metrics\HeartRateJoggingMetric::class,
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
