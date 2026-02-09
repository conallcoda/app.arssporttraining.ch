<?php

namespace App\Data\Exercise;

enum ExerciseDimensions: string
{
    case Reps = 'reps';
    case Weight = 'weight';
    case Duration = 'duration';
    case Rest = 'rest';
    case Tempo = 'tempo';
    case Pace = 'pace';
    case Watts = 'watts';
}
