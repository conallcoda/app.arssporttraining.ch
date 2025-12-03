<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\Computed;

class AthleteTestData extends AbstractData
{
    #[Computed]
    public float $oneRepMax;

    public function __construct(
        public int $exerciseId,
        public int $reps,
        public float $weight,
    ) {
        $this->oneRepMax = OneRepMaxConverter::getOneRepMax(
            reps: $this->reps,
            weight: $this->weight,
        );
    }

    public static function back_squat(): self
    {
        return new self(
            exerciseId: 1,
            reps: 8,
            weight: 45.0,
        );
    }
}
