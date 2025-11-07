<?php

namespace App\Models\Exercise;

enum Level: string
{
    case BEGINNER = 'beginner';
    case INTERMEDIATE = 'intermediate';
    case EXPERT = 'expert';
}
