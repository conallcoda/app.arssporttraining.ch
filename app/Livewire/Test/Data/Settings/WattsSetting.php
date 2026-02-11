<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Form\Fields;

class WattsSetting extends AbstractSetting
{
    public static function fields(): array
    {
        return [
            Fields\Number::make('default')
                ->label('Default Watts')
                ->default(100)
                ->min(0)
                ->suffix('W')
                ->live(),
        ];
    }
}
