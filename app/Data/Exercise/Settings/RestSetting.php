<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use Coda\Cms\Form\Fields;

class RestSetting extends AbstractSetting
{
    public function __construct(
        public int $default = 60,
        public string $applyPer = 'week',
    ) {}

    public static function unitLabel(): string
    {
        return 's';
    }

    public static function inputMeta(array $config = []): CellInputMeta
    {
        return new CellInputMeta(
            inputType: 'number',
            inputStep: '5',
            min: 0,
        );
    }

    public static function fields(): array
    {
        return [
            Fields\Number::make('default')
                ->label('Default Rest')
                ->default(60)
                ->min(0)
                ->suffix('sec'),
            ApplyPerField::make('week'),
        ];
    }
}
