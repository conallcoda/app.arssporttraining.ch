<?php

namespace App\Data\Exercise;

enum ExerciseType: string
{
    case Strength = 'strength';
    case Cardio = 'cardio';
    case Mobility = 'mobility';

    public function getFieldsClass(): ?string
    {
        return match ($this) {
            self::Strength => Types\StrengthExerciseConfig::class,
        };
    }
}
