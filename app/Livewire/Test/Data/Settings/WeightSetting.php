<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Form\Fields;

class WeightSetting extends AbstractSetting
{
    public static function fields(): array
    {
        return [
            Fields\RadioSegmented::make('mode')
                ->label('Mode')
                ->options([
                    'automatic' => 'Automatic',
                    'manual' => 'Manual',
                ])
                ->default('automatic')
                ->live(),
            Fields\Percentage::make('oneRepMaxModifier')
                ->label('1RM Modifier')
                ->default(100)
                ->live()
                ->show('mode == "automatic"'),
            Fields\Weight::make('defaultWeight')
                ->label('Default Weight')
                ->default(5)
                ->live()
                ->show('mode == "manual"'),
        ];
    }
}
