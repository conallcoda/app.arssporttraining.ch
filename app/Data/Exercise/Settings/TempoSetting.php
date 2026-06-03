<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use App\Support\Training\ApplyPerScope;
use Coda\FormKit\Fields;

class TempoSetting extends AbstractSetting
{
    public function __construct(
        public ?string $default = '3010',
        public string $applyPer = ApplyPerScope::FORM_SESSION,
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
                ->rules('nullable|regex:/^\d{4}$/'),
            ApplyPerField::make(ApplyPerScope::FORM_SESSION),
        ];
    }
}
