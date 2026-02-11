<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Form\Fields;

class DistanceSetting extends AbstractSetting
{
    public static function fields(): array
    {
        return [
            Fields\RadioSegmented::make('unit')
                ->label('Unit')
                ->options([
                    'meters' => 'Meters',
                    'kilometers' => 'Kilometers',
                ])
                ->default('meters')
                ->live(),
            Fields\Number::make('default')
                ->label('Default Distance')
                ->defaultMap([
                    'meters' => 500,
                    'kilometers' => 1,
                ])
                ->min(0)
                ->live()
                ->suffixMap([
                    'meters' => 'm',
                    'kilometers' => 'km',
                ]),
        ];
    }
}
