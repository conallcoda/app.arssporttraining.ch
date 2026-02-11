<?php

namespace App\Models\Exercise;

enum ExerciseExternalTemplate: string
{
    case Weight = 'weight';
    case Conditioning = 'conditioning';
    case Timed = 'timed';

    public function label(): string
    {
        return match ($this) {
            self::Weight => 'Weight',
            self::Conditioning => 'Conditioning',
            self::Timed => 'Timed',
        };
    }
}
