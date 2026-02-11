<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Form\Fields;

class TempoSetting extends AbstractSetting
{
    public static function fields(): array
    {
        return [
            Fields\Text::make('default')
                ->label('Default Tempo')
                ->default('3010')
                ->live(),
        ];
    }
}
