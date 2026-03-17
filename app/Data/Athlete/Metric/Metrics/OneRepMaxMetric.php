<?php

namespace App\Data\Athlete\Metric\Metrics;

use App\Data\Athlete\Metric\AbstractMetric;
use App\Form\Fields\Athlete\MeasuredReps;
use App\Form\Fields\Athlete\MeasuredWeight;
use App\Training\Reference\OneRepMaxConversion;

class OneRepMaxMetric extends AbstractMetric
{
    public function __construct(
        public ?int $measuredReps = 1,
        public ?float $measuredWeight = 50,
    ) {}

    public static function fields(): array
    {
        return [
            MeasuredReps::make('measuredReps'),
            MeasuredWeight::make('measuredWeight'),
        ];
    }

    /** @return array<string, string> */
    public static function derivedValues(array $fieldValues): array
    {
        $reps = (int) ($fieldValues['measuredReps'] ?? 1);
        $weight = (float) ($fieldValues['measuredWeight'] ?? 0);

        return [
            'estimated1RM' => (string) OneRepMaxConversion::estimatedOneRepMax($reps, $weight),
        ];
    }
}
