<?php

namespace App\Models\Exercise;

enum Force: string
{
    case PULL = 'pull';
    case PUSH = 'push';
    case STATIC = 'static';
}
