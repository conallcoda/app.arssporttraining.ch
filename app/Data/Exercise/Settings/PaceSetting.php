<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use App\Support\Training\ApplyPerScope;
use Coda\FormKit\Fields;

class PaceSetting extends AbstractSetting
{
    public function __construct(
        public ?string $default = '5:00',
        public string $applyPer = ApplyPerScope::FORM_SET,
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

    /** @return list<array{label: string, modalField: string}> */
    public function badges(): array
    {
        if ($this->default === null || $this->default === '' || $this->default === '0:00') {
            return [];
        }

        return [
            ['label' => $this->default.'/km', 'modalField' => static::fieldsetKey()],
        ];
    }

    public static function fields(): array
    {
        return [
            Fields\Duration::make('default')
                ->label('Default Pace')
                ->default('5:00')
                ->defaultUnit('mm:ss')
                ->suffix(static::unitLabel()),
            ApplyPerField::make(),
        ];
    }
}
