<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use App\Form\Fields\HeartRate;
use App\Support\Training\ApplyPerScope;
use Coda\FormKit\Fields;

class HeartRateSetting extends AbstractSetting
{
    public function __construct(
        public string $mode = 'manual',
        public ?string $default = '140',
        public string $applyPer = ApplyPerScope::FORM_SET,
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
            HeartRate::make('default')
                ->label('Default Heart Rate')
                ->default('140')
                ->rules('nullable|regex:/^\d{1,3}(?:-\d{1,3})?$/')
                ->show('mode == "manual"'),
            ApplyPerField::make()
                ->show('mode == "manual"'),
        ];
    }

    public static function athleteCanonicalValue(mixed $value, array $config = []): ?array
    {
        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value))) {
            $numeric = (int) $value;

            return [
                'kind' => 'heart_rate',
                'format' => 'scalar',
                'display' => (string) $value,
                'value' => $numeric,
                'min' => $numeric,
                'max' => $numeric,
            ];
        }

        if (! is_string($value) || ! preg_match('/^(?<min>\d+)-(?<max>\d+)$/', $value, $matches)) {
            return null;
        }

        return [
            'kind' => 'heart_rate',
            'format' => 'range',
            'display' => $value,
            'min' => (int) $matches['min'],
            'max' => (int) $matches['max'],
        ];
    }
}
