<?php

namespace App\Data\Exercise\Settings;

use App\Cms\Form\Fields;
use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;

class PaceSetting extends AbstractSetting
{
    public function __construct(
        public string $default = '5:00',
        public string $applyPer = 'session',
    ) {}

    public static function unitLabel(): string
    {
        return 'mm:ss/km';
    }

    public static function inputMeta(array $config = []): CellInputMeta
    {
        return new CellInputMeta(
            inputType: 'text',
            maxlength: 5,
            mask: '9:99',
        );
    }

    public static function fields(): array
    {
        return [
            Fields\Duration::make('default')
                ->label('Default Pace')
                ->default('5:00')
                ->defaultUnit('mm:ss')
                ->suffix(static::unitLabel())
                ->live(),
            ApplyPerField::make(),
        ];
    }
}
