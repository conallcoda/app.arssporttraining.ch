<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Form\Fields;

class DurationSetting extends AbstractSetting
{
    public function __construct(
        public string $unit = 'seconds',
        public int|string $default = 60,
        public string $applyPer = 'session',
    ) {}

    public static function fields(): array
    {
        return [
            Fields\RadioSegmented::make('unit')
                ->label('Unit')
                ->options([
                    'seconds' => 'Seconds',
                    'minutes' => 'Minutes',
                    'mm:ss' => 'mm:ss',
                ])
                ->default('seconds')
                ->live(),
            Fields\Duration::make('default')
                ->label('Default Duration')
                ->defaultMap([
                    'seconds' => 60,
                    'minutes' => 1,
                    'mm:ss' => '1:00',
                ])
                ->unit('unit')
                ->live(),
            ApplyPerField::make(),
        ];
    }
}
