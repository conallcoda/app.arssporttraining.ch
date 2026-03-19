<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use Coda\Cms\Form\Fields;

class HeartRateSetting extends AbstractSetting
{
    public function __construct(
        public string $mode = 'manual',
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

    /** @return list<array{label: string, modalField: string}> */
    public function badges(): array
    {
        if ($this->mode === 'automatic_biking') {
            return [
                ['label' => 'Auto (Biking)', 'modalField' => static::fieldsetKey()],
            ];
        }

        if ($this->mode === 'automatic_jogging') {
            return [
                ['label' => 'Auto (Jogging)', 'modalField' => static::fieldsetKey()],
            ];
        }

        if ($this->default === null || $this->default === '') {
            return [];
        }

        return [
            ['label' => $this->default.' bpm', 'modalField' => static::fieldsetKey()],
        ];
    }

    public static function fields(): array
    {
        return [
            Fields\RadioSegmented::make('mode')
                ->label('Mode')
                ->options([
                    'manual' => 'Manual',
                    'automatic_biking' => 'Auto (Biking)',
                    'automatic_jogging' => 'Auto (Jogging)',
                ])
                ->default('manual')
                ->live(),
            Fields\HeartRate::make('default')
                ->label('Default Heart Rate')
                ->default('140')
                ->show('mode == "manual"'),
            ApplyPerField::make()
                ->show('mode == "manual"'),
        ];
    }
}
