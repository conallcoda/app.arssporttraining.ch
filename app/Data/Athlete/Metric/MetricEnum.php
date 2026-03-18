<?php

namespace App\Data\Athlete\Metric;

enum MetricEnum: string
{
    case OneRepMax = 'oneRepMax';
    case MaxHeartRateBiking = 'maxHeartRateBiking';
    case MaxHeartRateJogging = 'maxHeartRateJogging';

    public function label(): string
    {
        return match ($this) {
            self::OneRepMax => '1RM',
            self::MaxHeartRateBiking => 'Max HR Biking',
            self::MaxHeartRateJogging => 'Max HR Jogging',
        };
    }

    /** @return class-string<AbstractMetric> */
    public function metricClass(): string
    {
        return match ($this) {
            self::OneRepMax => Metrics\OneRepMaxMetric::class,
            self::MaxHeartRateBiking => Metrics\MaxHeartRateBikingMetric::class,
            self::MaxHeartRateJogging => Metrics\MaxHeartRateJoggingMetric::class,
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
