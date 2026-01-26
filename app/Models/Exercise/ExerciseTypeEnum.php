<?php

namespace App\Models\Exercise;

use App\Models\Exercise\ExerciseType\StrengthDefaults;

enum ExerciseTypeEnum: string
{
    case Strength = 'strength';

    public function getFieldsClass(): ?string
    {
        return match ($this) {
            self::Strength => StrengthDefaults::class,
        };
    }
}
