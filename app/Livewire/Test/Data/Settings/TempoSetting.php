<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Form\Fields;
use App\Livewire\Test\Data\Preview\CellInputMeta;

class TempoSetting extends AbstractSetting
{
    public function __construct(
        public string $default = '3010',
        public string $applyPer = 'week',
    ) {}

    public static function inputMeta(array $config = []): CellInputMeta
    {
        return new CellInputMeta(
            inputType: 'text',
            maxlength: 4,
            mask: '9999',
        );
    }

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
