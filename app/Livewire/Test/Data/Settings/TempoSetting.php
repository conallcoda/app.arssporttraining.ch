<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Form\Fields;

class TempoSetting extends AbstractSetting
{
    public function __construct(
        public string $default = '3010',
        public string $applyPer = 'week',
    ) {}

    public static function fields(): array
    {
        return [
            Fields\Text::make('default')
                ->label('Default Tempo')
                ->default('3010')
                ->live(),
            ApplyPerField::make('week'),
        ];
    }
}
