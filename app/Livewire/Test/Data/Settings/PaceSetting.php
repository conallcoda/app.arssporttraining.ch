<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Form\Fields;

class PaceSetting extends AbstractSetting
{
    public static function fields(): array
    {
        return [
            Fields\Duration::make('default')
                ->label('Default Pace')
                ->default('5:00')
                ->defaultUnit('mm:ss')
                ->suffix('mm:ss/km')
                ->live(),
        ];
    }
}
