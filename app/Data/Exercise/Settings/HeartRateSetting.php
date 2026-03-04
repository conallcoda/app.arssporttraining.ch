<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use Coda\Cms\Form\Fields;

class HeartRateSetting extends AbstractSetting
{
    public function __construct(
        public ?string $default = '140',
        public string $applyPer = 'session',
    ) {}

    public static function unitLabel(): string
    {
        return 'bpm';
    }

    public static function inputMeta(array $config = []): CellInputMeta
    {
        return new CellInputMeta(
            inputType: 'text',
            maxlength: 7,
            pattern: '\d{1,3}(-\d{1,3})?',
        );
    }

    public static function fields(): array
    {
        return [
            Fields\HeartRate::make('default')
                ->label('Default Heart Rate')
                ->default('140'),
            ApplyPerField::make(),
        ];
    }
}
