<?php

namespace App\Data\Athlete\Metric\Metrics;

use App\Data\Athlete\Metric\AbstractMetric;
use App\Data\Athlete\Metric\ReadinessMetricData;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\BuiltinTypeCast;

class ReadinessMetric extends AbstractMetric
{
    public function __construct(
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $sleepMinutes = null,
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $sleepQuality = null,
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $altitudeMeters = null,
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $condition = null,
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $mood = null,
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $motivation = null,
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $soreness = null,
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $energy = null,
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $restingHeartRate = null,
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $restingHeartRateBaseline = null,
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $hrv = null,
    ) {}

    public static function fields(): array
    {
        return [];
    }

    public function summary(): string
    {
        $score = $this->data()->readinessScore();
        $label = ReadinessMetricData::trafficLightLabel($this->data()->trafficLight());

        if ($score === null || $label === null) {
            return 'Incomplete';
        }

        return number_format($score, 2).' '.$label;
    }

    /** @return array{label: string} */
    public function badge(string $prefix): array
    {
        $score = $this->data()->readinessScore();

        if ($score === null) {
            return ['label' => "{$prefix}: Incomplete"];
        }

        return ['label' => "{$prefix}: ".number_format($score, 2).' / 5'];
    }

    /** @return array<string, string> */
    public static function derivedValues(array $fieldValues): array
    {
        $metric = self::from($fieldValues);
        $data = $metric->data();
        $score = $data->readinessScore();
        $trafficLight = $data->trafficLight();

        return array_filter([
            'sleepDurationScore' => self::stringifyNullable(ReadinessMetricData::sleepDurationScore($metric->sleepMinutes)),
            'sleepDurationLabel' => ReadinessMetricData::sleepDurationLabel($metric->sleepMinutes),
            'altitudeScore' => self::stringifyNullable(ReadinessMetricData::altitudeScore($metric->altitudeMeters)),
            'altitudeLabel' => ReadinessMetricData::altitudeLabel($metric->altitudeMeters),
            'rhrScore' => self::stringifyNullable(ReadinessMetricData::rhrScore($metric->restingHeartRate, $metric->restingHeartRateBaseline)),
            'sleepScore' => self::stringifyNullable($data->sleepScore()),
            'readinessComponentsSum' => self::stringifyNullable($data->readinessComponentsSum()),
            'readinessScore' => self::stringifyNullable($score),
            'trafficLight' => $trafficLight,
            'trafficLightLabel' => ReadinessMetricData::trafficLightLabel($trafficLight),
            'trafficLightColor' => ReadinessMetricData::trafficLightColor($trafficLight),
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function data(): ReadinessMetricData
    {
        return new ReadinessMetricData(
            sleepMinutes: $this->sleepMinutes,
            sleepQuality: $this->sleepQuality,
            altitudeMeters: $this->altitudeMeters,
            condition: $this->condition,
            mood: $this->mood,
            motivation: $this->motivation,
            soreness: $this->soreness,
            energy: $this->energy,
            restingHeartRate: $this->restingHeartRate,
            restingHeartRateBaseline: $this->restingHeartRateBaseline,
            hrv: $this->hrv,
        );
    }

    private static function stringifyNullable(int|float|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
