<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Form\Fields;

class RestSetting extends AbstractSetting
{
    public static function fields(): array
    {
        return [
            Fields\Number::make('default')
                ->label('Default Rest')
                ->default(60)
                ->min(0)
                ->suffix('sec')
                ->live(),
        ];
    }
}
