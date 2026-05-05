<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use App\Support\Training\ApplyPerScope;
use Coda\FormKit\Fields;

class DistanceSetting extends AbstractSetting
{
    public function __construct(
        public string $unit = 'meters',
        public int|float|null $default = 500,
        public string $applyPer = ApplyPerScope::FORM_SET,
    ) {}

    public static function unitLabel(): array
    {
        return ['meters' => 'm', 'kilometers' => 'km'];
    }

    public static function inputMeta(array $config = []): CellInputMeta
    {
        $unit = $config['unit'] ?? 'meters';

        return new CellInputMeta(
            inputType: 'number',
            inputStep: $unit === 'kilometers' ? '0.1' : '1',
            min: 0,
        );
    }

    public static function fields(): array
    {
        return [
            Fields\RadioSegmented::make('unit')
                ->label('Unit')
                ->options([
                    'meters' => 'Meters',
                    'kilometers' => 'Kilometers',
                ])
                ->default('meters')
                ->live(),
            Fields\Number::make('default')
                ->label('Default Distance')
                ->defaultMap([
                    'meters' => 500,
                    'kilometers' => 1,
                ])
                ->min(0)
                ->suffixMap(static::unitLabel()),
            ApplyPerField::make(),
        ];
    }
}
