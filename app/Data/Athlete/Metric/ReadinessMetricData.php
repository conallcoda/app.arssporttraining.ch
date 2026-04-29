<?php

namespace App\Data\Athlete\Metric;

use Coda\Cms\Data\AbstractData;

class ReadinessMetricData extends AbstractData
{
    public const DEFAULT_EXTREME_OFFSET = -5;

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

    public static function adjustScore(int|float|null $score, int $extremeOffset = self::DEFAULT_EXTREME_OFFSET): int|float|null
    {
        if ($score === null) {
            return null;
        }

        return $score <= 1 ? $extremeOffset : $score;
    }

    public function sleepScore(int $extremeOffset = self::DEFAULT_EXTREME_OFFSET): ?float
    {
        $durationScore = self::sleepDurationScore($this->sleepMinutes);
        $altitudeScore = self::altitudeScore($this->altitudeMeters);

        if ($this->sleepQuality === null || $durationScore === null || $altitudeScore === null) {
            return null;
        }

        return (self::adjustScore($this->sleepQuality, $extremeOffset) * self::SLEEP_WEIGHT_QUALITY)
            + (self::adjustScore($durationScore, $extremeOffset) * self::SLEEP_WEIGHT_DURATION)
            + (self::adjustScore($altitudeScore, $extremeOffset) * self::SLEEP_WEIGHT_ALTITUDE);
    }

    public function readinessComponentsSum(int $extremeOffset = self::DEFAULT_EXTREME_OFFSET): ?float
    {
        $components = [
            $this->sleepScore($extremeOffset),
            self::adjustScore($this->condition, $extremeOffset),
            self::adjustScore($this->mood, $extremeOffset),
            self::adjustScore($this->motivation, $extremeOffset),
            self::adjustScore($this->soreness, $extremeOffset),
            self::adjustScore($this->energy, $extremeOffset),
            self::adjustScore(self::rhrScore($this->restingHeartRate, $this->restingHeartRateBaseline), $extremeOffset),
        ];

        foreach ($components as $component) {
            if ($component === null) {
                return null;
            }
        }

        return array_sum($components);
    }

    public function readinessScore(int $extremeOffset = self::DEFAULT_EXTREME_OFFSET): ?float
    {
        $sum = $this->readinessComponentsSum($extremeOffset);

        if ($sum === null) {
            return null;
        }

        return $sum / 7;
    }

    public function trafficLight(int $extremeOffset = self::DEFAULT_EXTREME_OFFSET): ?string
    {
        $score = $this->readinessScore($extremeOffset);

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

    public static function trafficLightLabel(?string $trafficLight): ?string
    {
        return match ($trafficLight) {
            'ready' => 'Ready',
            'train_smart' => 'Train Smart',
            'recovery' => 'Recovery',
            'rest' => 'Rest',
            default => null,
        };
    }

    public static function trafficLightColor(?string $trafficLight): ?string
    {
        return match ($trafficLight) {
            'ready' => 'green',
            'train_smart' => 'amber',
            'recovery' => 'orange',
            'rest' => 'red',
            default => null,
        };
    }

    public function rhrDelta(): ?int
    {
        if ($this->restingHeartRate === null || $this->restingHeartRateBaseline === null) {
            return null;
        }

        return $this->restingHeartRate - $this->restingHeartRateBaseline;
    }

    public static function formatSleepDuration(?int $minutes): string
    {
        if ($minutes === null) {
            return '';
        }

        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
