<?php

namespace App\Models\Exercise;

use Filament\Support\Contracts\HasLabel;

enum Level: string implements HasLabel
{
    case BEGINNER = 'beginner';
    case INTERMEDIATE = 'intermediate';
    case EXPERT = 'expert';

    public function getLabel(): string
    {
        return $this->value;
    }
}
