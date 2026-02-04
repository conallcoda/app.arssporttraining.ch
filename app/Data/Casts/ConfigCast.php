<?php

namespace App\Data\Casts;

use App\Data\AbstractConfig;
use App\Data\Exercise\ExerciseType;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class ConfigCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): ?AbstractConfig
    {
        if ($value === null) {
            return null;
        }

        $type = $properties['type'] ?? null;

        if (! $type instanceof ExerciseType) {
            $type = ExerciseType::tryFrom($type);
        }

        if (! $type) {
            return null;
        }

        $configClass = $type->getConfigClass();

        return $configClass::from($value);
    }
}
