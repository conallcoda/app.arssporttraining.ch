<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use Coda\Cms\Form\Fields;

class HeartRateZoneSetting extends AbstractSetting
{
    public function __construct(
        public ?string $default = '2',
        public string $applyPer = 'session',
    ) {}

    public static function unitLabel(): string
    {
        return 'zone';
    }

    public static function inputMeta(array $config = []): CellInputMeta
    {
        return new CellInputMeta(
            inputType: 'text',
            maxlength: 3,
            pattern: '[0-4](-[0-4])?',
        );
    }

    public function badges(): array
    {
        if ($this->default === null || $this->default === '') {
            return [];
        }

        return [
            ['label' => 'Zone '.$this->default, 'modalField' => static::fieldsetKey()],
        ];
    }

    public static function fields(): array
    {
        return [
            Fields\HeartRateZone::make('default')
                ->label('Default Zone')
                ->default('2'),
            ApplyPerField::make(),
        ];
    }
}
