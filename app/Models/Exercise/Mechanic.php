<?php

namespace App\Models\Exercise;

enum Mechanic: string
{
    case COMPOUND = 'compound';
    case ISOLATION = 'isolation';
}
