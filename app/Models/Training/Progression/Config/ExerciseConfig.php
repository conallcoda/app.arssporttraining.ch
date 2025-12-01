<?php

namespace App\Models\Training\Progression\Config;

use App\Data\AbstractData;

class ExerciseConfig extends AbstractData
{
    public function __construct(
        public int $exerciseId,
        public ?string $weightStrategy = null,
        public ?string $repStrategy = null,
        public ?float $targetImprovement = null,
        public ?int $startingReps = null,
        public ?int $stepDownInterval = null,
        public ?int $repDecrement = null,
        public ?int $minimumReps = null,
        public ?float $incrementStep = null,
        public float $modifier = 1.0,
    ) {}

    public function hasOverride(string $key): bool
    {
        return $this->{$key} !== null;
    }

    public function getOverrides(): array
    {
        $overrides = [];

        foreach (['weightStrategy', 'repStrategy', 'targetImprovement', 'startingReps', 'stepDownInterval', 'repDecrement', 'minimumReps', 'incrementStep'] as $key) {
            if ($this->{$key} !== null) {
                $overrides[$key] = $this->{$key};
            }
        }

        if ($this->modifier !== 1.0) {
            $overrides['modifier'] = $this->modifier;
        }

        return $overrides;
    }
}
