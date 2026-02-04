<?php

namespace App\Data\Exercise;

enum ExerciseType: string
{
    case Strength = 'strength';
    case Cardio = 'cardio';

    public function getConfigClass(): string
    {
        return match ($this) {
            self::Strength => Types\StrengthExerciseConfig::class,
            self::Cardio => Types\CardioExerciseConfig::class,
        };
    }

    public function getFields(): array
    {
        return $this->getConfigClass()::getFields();
    }
}
