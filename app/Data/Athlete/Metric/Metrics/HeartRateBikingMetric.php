<?php

namespace App\Data\Athlete\Metric\Metrics;

use App\Data\Athlete\Metric\AbstractMetric;
use App\Form\Fields\Athlete\AnaerobicThreshold;
use App\Form\Fields\Athlete\HeartRate;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\BuiltinTypeCast;

class HeartRateBikingMetric extends AbstractMetric
{
    public function __construct(
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $heartRate = null,
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $anaerobicThreshold = 90,
    ) {}

    public static function fields(): array
    {
        return [
            HeartRate::make('heartRate'),
            AnaerobicThreshold::make('anaerobicThreshold'),
        ];
    }

    public function summary(): string
    {
        return "{$this->heartRate} HR - {$this->anaerobicThreshold}% IAT";
    }

    /** @return array{label: string} */
    public function badge(string $prefix): array
    {
        return ['label' => "{$prefix}: {$this->heartRate} / {$this->anaerobicThreshold}%"];
    }

    /** @return array<string, string> */
    public static function derivedValues(array $fieldValues): array
    {
        return [];
    }
}
