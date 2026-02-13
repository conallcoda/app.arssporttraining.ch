<?php

namespace App\Data\Exercise\Settings;

use App\Cms\Form\Fields;
use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;

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
