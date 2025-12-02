<?php

namespace App\Models\Training\Progression\Casts;

use App\Models\Training\Progression\Strategy\Rep\PairedLadderRepConfig;
use App\Models\Training\Progression\Strategy\Rep\ProportionalRepConfig;
use App\Models\Training\Progression\Strategy\Rep\RepConfigInterface;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class RepConfigCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): RepConfigInterface
    {
        if ($value instanceof RepConfigInterface) {
            return $value;
        }

        if (is_array($value)) {
            $class = $value['class'] ?? PairedLadderRepConfig::class;
            unset($value['class']);

            return match ($class) {
                ProportionalRepConfig::class => ProportionalRepConfig::from($value),
                default => PairedLadderRepConfig::from($value),
            };
        }

        return new PairedLadderRepConfig;
    }
}
