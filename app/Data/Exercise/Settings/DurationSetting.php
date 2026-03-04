<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use Coda\Cms\Form\Fields;

class DurationSetting extends AbstractSetting
{
    public function __construct(
        public string $unit = 'seconds',
        public int|string|null $default = 60,
        public string $applyPer = 'session',
    ) {}

    public static function unitLabel(): array
    {
        return ['seconds' => 's', 'minutes' => 'm', 'mm:ss' => 'mm:ss'];
    }

    public static function inputMeta(array $config = []): CellInputMeta
    {
        $unit = $config['unit'] ?? 'seconds';

        if ($unit === 'mm:ss') {
            return new CellInputMeta(
                inputType: 'text',
                maxlength: 5,
                mask: '9:99',
            );
        }

        return new CellInputMeta(
            inputType: 'number',
            inputStep: '1',
            min: 0,
        );
    }

    /** @return list<array{label: string, modalField: string}> */
    public function badges(): array
    {
        if ($this->default === null || $this->default === '' || $this->default === 0 || $this->default === '0:00') {
            return [];
        }

        $unitLabel = $this->unit === 'mm:ss' ? '' : (static::resolveUnitLabel($this->toArray()) ?? '');

        return [
            ['label' => $this->default.$unitLabel, 'modalField' => static::fieldsetKey()],
        ];
    }

    public static function fields(): array
    {
        return [
            Fields\RadioSegmented::make('unit')
                ->label('Unit')
                ->options([
                    'seconds' => 'Seconds',
                    'minutes' => 'Minutes',
                    'mm:ss' => 'mm:ss',
                ])
                ->default('seconds')
                ->live(),
            Fields\Duration::make('default')
                ->label('Default Duration')
                ->defaultMap([
                    'seconds' => 60,
                    'minutes' => 1,
                    'mm:ss' => '1:00',
                ])
                ->unit('unit'),
            ApplyPerField::make(),
        ];
    }
}
