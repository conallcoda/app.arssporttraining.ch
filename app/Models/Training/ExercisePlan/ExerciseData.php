<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;

class ExerciseData extends AbstractData
{
    public function __construct(
        public int $id,
        public string $name,
        public float $modifier,
        public string $group,
    ) {}

    public static function back_squat(): self
    {
        return new self(
            id: 1,
            name: 'Back Squat',
            modifier: 100.0,
            group: 'Strength 1',
        );
    }

    public static function front_squat(): self
    {
        return new self(
            id: 2,
            name: 'Front Squat',
            modifier: 85.0,
            group: 'Strength 1',
        );
    }

    public static function deadlift(): self
    {
        return new self(
            id: 3,
            name: 'Deadlift',
            modifier: 90.0,
            group: 'Strength 2',
        );
    }

    public static function press(): self
    {
        return new self(
            id: 4,
            name: 'Press',
            modifier: 80.0,
            group: 'Strength 2',
        );
    }
}
