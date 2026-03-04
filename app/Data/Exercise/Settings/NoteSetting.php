<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use Coda\Cms\Form\Fields;

class NoteSetting extends AbstractSetting
{
    public function __construct(
        public ?string $default = '',
        public ?string $label = '',
        public string $applyPer = 'session',
    ) {}

    public static function inputMeta(array $config = []): CellInputMeta
    {
        return new CellInputMeta(
            inputType: 'text',
        );
    }

    public static function fields(): array
    {
        return [
            Fields\Text::make('default')
                ->label('Default')
                ->default(''),
            Fields\Text::make('label')
                ->label('Label')
                ->placeholder('Note')
                ->default(''),
            ApplyPerField::make(),
        ];
    }
}
