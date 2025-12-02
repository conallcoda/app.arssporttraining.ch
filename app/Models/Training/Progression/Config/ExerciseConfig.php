<?php

namespace App\Models\Training\Progression\Config;

use App\Data\AbstractData;
use App\Models\Training\Progression\Casts\NullableRepConfigCast;
use App\Models\Training\Progression\Casts\NullableWeightConfigCast;
use App\Models\Training\Progression\Strategy\Rep\RepConfigInterface;
use App\Models\Training\Progression\Strategy\Weight\WeightConfigInterface;
use Spatie\LaravelData\Attributes\WithCast;

class ExerciseConfig extends AbstractData
{
    public function __construct(
        public int $exerciseId,
        #[WithCast(NullableWeightConfigCast::class)]
        public ?WeightConfigInterface $weightConfig = null,
        #[WithCast(NullableRepConfigCast::class)]
        public ?RepConfigInterface $repConfig = null,
        public float $modifier = 1.0,
    ) {}

    public function hasOverride(string $key): bool
    {
        return $this->{$key} !== null;
    }

    public function getOverrides(): array
    {
        $overrides = [];

        if ($this->weightConfig !== null) {
            $overrides['weightConfig'] = $this->weightConfig;
        }

        if ($this->repConfig !== null) {
            $overrides['repConfig'] = $this->repConfig;
        }

        if ($this->modifier !== 1.0) {
            $overrides['modifier'] = $this->modifier;
        }

        return $overrides;
    }
}
