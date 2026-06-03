<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use App\Support\Training\ApplyPerScope;
use Coda\FormKit\Fields;

class WattsSetting extends AbstractSetting
{
    public function __construct(
        public ?int $default = 100,
        public string $applyPer = ApplyPerScope::FORM_SET,
    ) {}

    public static function unitLabel(): string
    {
        return 'W';
    }

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
                ->rules('nullable|integer|min:0')
                ->suffix('W'),
            ApplyPerField::make(),
        ];
    }
}
