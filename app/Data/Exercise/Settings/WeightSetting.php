<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use App\Form\Fields\Weight;
use App\Support\Training\ApplyPerScope;
use Coda\FormKit\Fields;

class WeightSetting extends AbstractSetting
{
    public function __construct(
        public string $mode = 'manual',
        public ?int $oneRepMaxModifier = 100,
        public ?float $default = 5,
        public string $applyPer = ApplyPerScope::FORM_SET,
    ) {}

    public static function unitLabel(): string
    {
        return 'kg';
    }

    public static function inputMeta(array $config = []): CellInputMeta
    {
        return new CellInputMeta(
            inputType: 'number',
            inputStep: '0.5',
            min: 0,
        );
    }

    /** @return list<array{label: string, modalField: string}> */
    public function badges(): array
    {
        if ($this->mode === 'automatic') {
            if ($this->oneRepMaxModifier === null) {
                return [];
            }

            return [
                ['label' => $this->oneRepMaxModifier.'%', 'modalField' => static::fieldsetKey()],
            ];
        }

        if ($this->default === null) {
            return [];
        }

        return [
            ['label' => $this->default.'kg', 'modalField' => static::fieldsetKey()],
        ];
    }

    public static function fields(): array
    {
        return [
            Fields\RadioSegmented::make('mode')
                ->label('Mode')
                ->options([
                    'automatic' => 'Automatic',
                    'manual' => 'Manual',
                ])
                ->default('manual')
                ->live(),
            Fields\Percentage::make('oneRepMaxModifier')
                ->label('1RM Modifier')
                ->default(100)
                ->show('mode == "automatic"'),
            Weight::make('default')
                ->label('Default Weight')
                ->default(5)
                ->show('mode == "manual"'),
            ApplyPerField::make()
                ->show('mode == "manual"'),
        ];
    }
}
