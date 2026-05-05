<?php

namespace App\Form\Fields\Exercise;

use Coda\FormKit\Fields;

class ApplyPerField
{
    public static function make(string $default = 'per_set'): Fields\RadioSegmented
    {
        return Fields\RadioSegmented::make('applyPer')
            ->label('Apply Per')
            ->options([
                'per_session' => 'Session',
                'per_set' => 'Set',
            ])
            ->default($default)
            ->live();
    }
}
