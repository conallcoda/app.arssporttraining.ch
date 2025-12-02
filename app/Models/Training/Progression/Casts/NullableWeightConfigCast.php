<?php

namespace App\Models\Training\Progression\Casts;

use App\Models\Training\Progression\Strategy\Weight\CompoundedWeightConfig;
use App\Models\Training\Progression\Strategy\Weight\FixedStepWeightConfig;
use App\Models\Training\Progression\Strategy\Weight\WeightConfigInterface;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class NullableWeightConfigCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): ?WeightConfigInterface
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof WeightConfigInterface) {
            return $value;
        }

        if (is_array($value)) {
            $class = $value['class'] ?? FixedStepWeightConfig::class;
            unset($value['class']);

            return match ($class) {
                CompoundedWeightConfig::class => CompoundedWeightConfig::from($value),
                default => FixedStepWeightConfig::from($value),
            };
        }

        return null;
    }
}
