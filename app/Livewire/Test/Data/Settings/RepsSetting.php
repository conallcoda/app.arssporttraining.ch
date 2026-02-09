<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Form\Fields;

class RepsSetting extends AbstractSetting
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
            Fields\Number::make('startingReps')
                ->label('Starting Reps')
                ->default(10)
                ->suffix('rep(s)')
                ->live()
                ->show('mode == "automatic"'),
            Fields\Number::make('stepDownInterval')
                ->label('Step Down Interval')
                ->default(2)
                ->live()
                ->show('mode == "automatic"'),
            Fields\Number::make('repDecrement')
                ->label('Rep Decrement')
                ->default(2)
                ->suffix('rep(s)')
                ->live()
                ->show('mode == "automatic"'),
            Fields\Number::make('minimumReps')
                ->label('Minimum Reps')
                ->default(1)
                ->suffix('rep(s)')
                ->live()
                ->show('mode == "automatic"'),
            Fields\Number::make('defaultReps')
                ->label('Default Reps')
                ->default(5)
                ->suffix('rep(s)')
                ->live()
                ->show('mode == "manual"'),
        ];
    }
}
