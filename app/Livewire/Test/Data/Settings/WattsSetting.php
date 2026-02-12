<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Form\Fields;
use App\Livewire\Test\Data\Preview\CellInputMeta;

class WattsSetting extends AbstractSetting
{
    public function __construct(
        public int $default = 100,
        public string $applyPer = 'session',
    ) {}

    public static function inputMeta(array $config = []): CellInputMeta
    {
        return new CellInputMeta(
            inputType: 'number',
            inputStep: '1',
            min: 0,
        );
    }

    public static function fields(): array
    {
        return [
            Fields\Number::make('default')
                ->label('Default Watts')
                ->default(100)
                ->min(0)
                ->suffix('W')
                ->live(),
            ApplyPerField::make(),
        ];
    }
}
