<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;

class ExerciseData extends AbstractData
{
    public function __construct(
        public int $id,
        public string $name,
        public float $modifier,
    ) {}

    public static function back_squat(): self
    {
        return new self(
            id: 1,
            name: 'Back Squat',
            modifier: 100.0,
        );
    }

    public static function front_squat(): self
    {
        return new self(
            id: 2,
            name: 'Front Squat',
            modifier: 85.0,
        );
    }
}
