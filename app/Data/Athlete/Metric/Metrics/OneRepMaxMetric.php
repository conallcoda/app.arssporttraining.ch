<?php

namespace App\Data\Athlete\Metric\Metrics;

use App\Data\Athlete\Metric\AbstractMetric;
use App\Form\Fields\Athlete\MeasuredReps;
use App\Form\Fields\Athlete\MeasuredWeight;
use App\Training\Reference\OneRepMaxConversion;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\BuiltinTypeCast;

class OneRepMaxMetric extends AbstractMetric
{
    public function __construct(
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $measuredReps = 1,
        #[WithCast(BuiltinTypeCast::class, 'float')]
        public ?float $measuredWeight = 50,
        #[WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $goalPercent = null,
    ) {}

    public static function fields(): array
    {
        return [
            MeasuredReps::make('measuredReps'),
            MeasuredWeight::make('measuredWeight'),
        ];
    }

    public function summary(): string
    {
        $estimated = $this->estimatedLabel();

        if ($this->goalPercent !== null && $this->goalPercent !== 0) {
            return "{$estimated}kg (+{$this->goalPercent}%)";
        }

        $weight = $this->measuredWeightLabel();
        $reps = $this->measuredReps ?? 1;

        return "{$estimated}kg ({$reps}x{$weight}kg)";
    }

    /** @return array{label: string} */
    public function badge(string $prefix): array
    {
        $estimated = $this->estimatedLabel();

        if ($this->goalPercent !== null && $this->goalPercent !== 0) {
            return ['label' => "{$prefix}: {$estimated}kg (+{$this->goalPercent}%)"];
        }

        return ['label' => "{$prefix}: {$estimated}kg"];
    }

    public function estimatedLabel(): string
    {
        return self::formatWeight(OneRepMaxConversion::estimatedOneRepMax(
            (int) $this->measuredReps,
            (float) $this->measuredWeight,
        ));
    }

    public function measuredWeightLabel(): string
    {
        return self::formatWeight($this->measuredWeight);
    }

    private static function formatWeight(?float $weight): string
    {
        return rtrim(rtrim(number_format((float) $weight, 1), '0'), '.');
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
