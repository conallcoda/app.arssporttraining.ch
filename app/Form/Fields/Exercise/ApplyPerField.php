<?php

namespace App\Form\Fields\Exercise;

use App\Cms\Form\Fields;

class ApplyPerField
{
    public static function make(string $default = 'session'): Fields\RadioSegmented
    {
        return Fields\RadioSegmented::make('applyPer')
            ->label('Apply Per')
            ->options([
                'session' => 'Session',
                'week' => 'Week',
            ])
            ->default($default)
            ->live();
    }
}
