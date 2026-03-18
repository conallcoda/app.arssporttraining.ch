<?php

namespace App\Data\Athlete\Metric\Metrics;

use App\Data\Athlete\Metric\AbstractMetric;
use App\Form\Fields\Athlete\AnaerobicThreshold;
use App\Form\Fields\Athlete\MaxHeartRate;

class MaxHeartRateJoggingMetric extends AbstractMetric
{
    public function __construct(
        public ?int $maxHeartRate = null,
        public ?int $anaerobicThreshold = 90,
    ) {}

    public static function fields(): array
    {
        return [
            MaxHeartRate::make('maxHeartRate'),
            AnaerobicThreshold::make('anaerobicThreshold'),
        ];
    }

    /** @return array<string, string> */
    public static function derivedValues(array $fieldValues): array
    {
        return [];
    }
}
