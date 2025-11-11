<?php

namespace App\Models\Exercise;

use Filament\Support\Contracts\HasLabel;

enum Mechanic: string implements HasLabel
{
    case COMPOUND = 'compound';
    case ISOLATION = 'isolation';

    public function getLabel(): string
    {
        return $this->value;
    }
}
