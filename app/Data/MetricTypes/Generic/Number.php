<?php

namespace App\Data\MetricTypes\Generic;

use App\Data\MetricTypes\AbstractMetricType;

class Number extends AbstractMetricType
{

    public function __construct(public ?float $step = null)
    {
        $this->step = $step ?? self::defaults()['step'];
    }

    public static function defaults(): array
    {
        return [
            'step' => 1,
        ];
    }

    public static function unit($short = true): ?string
    {
        return null;
    }

    public static function rules(): array
    {
        $step = static::defaults()['step'];

        $decimalPlaces = 0;
        if (is_float($step)) {
            $stepString = (string) $step;
            if (str_contains($stepString, '.')) {
                $decimalPlaces = strlen(substr($stepString, strpos($stepString, '.') + 1));
            }
        }

        return [
            'numeric',
            function ($attribute, $value, $fail) use ($step, $decimalPlaces) {
                if (!is_numeric($value)) {
                    return;
                }

                $multiplier = pow(10, $decimalPlaces);
                $scaledValue = round($value * $multiplier);
                $scaledStep = round($step * $multiplier);

                if ($scaledStep == 0 || fmod($scaledValue, $scaledStep) != 0) {
                    $fail("The {$attribute} must be a multiple of {$step}.");
                }
            },
        ];
    }
}
