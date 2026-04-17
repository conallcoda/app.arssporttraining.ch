<?php

namespace App\Data\Athlete\Metric;

use Coda\Cms\Data\AbstractData;

class ReadinessMetricData extends AbstractData
{
    private const SLEEP_WEIGHT_QUALITY = 0.40;

    private const SLEEP_WEIGHT_DURATION = 0.40;

    private const SLEEP_WEIGHT_ALTITUDE = 0.20;

    private const READY_THRESHOLD = 4.0;

    private const TRAIN_SMART_THRESHOLD = 3.0;

    private const RECOVERY_THRESHOLD = 2.0;

    public function __construct(
        public ?int $sleepMinutes = null,
        public ?int $sleepQuality = null,
        public ?int $altitudeMeters = null,
        public ?int $condition = null,
        public ?int $mood = null,
        public ?int $motivation = null,
        public ?int $soreness = null,
        public ?int $energy = null,
        public ?int $restingHeartRate = null,
        public ?int $restingHeartRateBaseline = null,
        public ?int $hrv = null,
    ) {}

    public static function sleepDurationScore(?int $minutes): ?int
    {
        if ($minutes === null) {
            return null;
        }

        return match (true) {
            $minutes >= 510 => 5,
            $minutes >= 450 => 4,
            $minutes >= 405 => 3,
            $minutes > 360 => 2,
            default => 1,
        };
    }

    public static function altitudeScore(?int $meters): ?int
    {
        if ($meters === null) {
            return null;
        }

        return match (true) {
            $meters <= 1500 => 5,
            $meters <= 1850 => 4,
            $meters <= 2400 => 3,
            $meters < 3000 => 2,
            default => 1,
        };
    }

    public static function sleepDurationLabel(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        return match (true) {
            $minutes >= 510 => 'Fully rested',
            $minutes >= 450 => 'Well rested',
            $minutes >= 405 => 'Adequate',
            $minutes > 360 => 'Short',
            default => 'Insufficient',
        };
    }

    public static function altitudeLabel(?int $meters): ?string
    {
        if ($meters === null) {
            return null;
        }

        return match (true) {
            $meters <= 1500 => 'Near sea level',
            $meters <= 1850 => 'Low',
            $meters <= 2400 => 'Moderate',
            $meters < 3000 => 'High',
            default => 'Very high',
        };
    }

    public static function rhrScore(?int $todayBpm, ?int $baselineBpm): ?int
    {
        if ($todayBpm === null || $baselineBpm === null) {
            return null;
        }

        $difference = abs($todayBpm - $baselineBpm);

        return match (true) {
            $difference < 5 => 5,
            $difference <= 6 => 4,
            $difference <= 8 => 3,
            $difference <= 10 => 2,
            default => 1,
        };
    }

    public function sleepScore(): ?float
    {
        $durationScore = self::sleepDurationScore($this->sleepMinutes);
        $altitudeScore = self::altitudeScore($this->altitudeMeters);

        if ($this->sleepQuality === null || $durationScore === null || $altitudeScore === null) {
            return null;
        }

        return ($this->sleepQuality * self::SLEEP_WEIGHT_QUALITY)
            + ($durationScore * self::SLEEP_WEIGHT_DURATION)
            + ($altitudeScore * self::SLEEP_WEIGHT_ALTITUDE);
    }

    public function readinessScore(): ?float
    {
        $components = [
            $this->sleepScore(),
            $this->condition,
            $this->mood,
            $this->motivation,
            $this->soreness,
            $this->energy,
            self::rhrScore($this->restingHeartRate, $this->restingHeartRateBaseline),
        ];

        foreach ($components as $component) {
            if ($component === null) {
                return null;
            }
        }

        return array_sum($components) / count($components);
    }

    public function trafficLight(): ?string
    {
        $score = $this->readinessScore();

        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= self::READY_THRESHOLD => 'ready',
            $score >= self::TRAIN_SMART_THRESHOLD => 'train_smart',
            $score >= self::RECOVERY_THRESHOLD => 'recovery',
            default => 'rest',
        };
    }

    public static function formatSleepDuration(?int $minutes): string
    {
        if ($minutes === null) {
            return '';
        }

        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
