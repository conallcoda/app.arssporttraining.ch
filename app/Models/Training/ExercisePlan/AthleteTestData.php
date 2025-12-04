<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;

class AthleteTestData extends AbstractData
{
    public function __construct(
        public int $exerciseId,
        public int $reps,
        public float $weight,
    ) {}

    public function getOneRepMaxAttribute(): float
    {
        return OneRepMaxConverter::getOneRepMax(
            reps: $this->reps,
            weight: $this->weight,
        );
    }

    public function __get(string $name): mixed
    {
        if ($name === 'oneRepMax') {
            return $this->getOneRepMaxAttribute();
        }

        return parent::__get($name);
    }

    public static function back_squat(int $reps = 8, float $weight = 45.0): self
    {
        return new self(
            exerciseId: 1,
            reps: $reps,
            weight: $weight,
        );
    }
}
