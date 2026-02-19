<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use Coda\Cms\Form\Fields;

class RepsSetting extends AbstractSetting
{
    public function __construct(
        public string $mode = 'automatic',
        public string|int $default = 10,
        public int $stepDownInterval = 2,
        public int $decrement = 2,
        public int $minimum = 1,
        public string $applyPer = 'session',
    ) {}

    public static function inputMeta(array $config = []): CellInputMeta
    {
        return new CellInputMeta(
            inputType: 'text',
            maxlength: 7,
            pattern: '\d+(_\d+)?',
        );
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
                ->default('automatic')
                ->live(),
            Fields\Reps::make('default')
                ->label('Default Reps')
                ->default(10)
                ->live(),
            Fields\Number::make('stepDownInterval')
                ->label('Step Down Interval')
                ->default(2)
                ->min(0)
                ->suffix('week(s)')
                ->live()
                ->show('mode == "automatic"'),
            Fields\Number::make('decrement')
                ->label('Rep Decrement')
                ->default(2)
                ->min(0)
                ->step(1)
                ->suffix('rep(s)')
                ->live()
                ->show('mode == "automatic"'),
            Fields\Number::make('minimum')
                ->label('Minimum Reps')
                ->default(1)
                ->min(1)
                ->step(1)
                ->suffix('rep(s)')
                ->live()
                ->show('mode == "automatic"'),
            ApplyPerField::make()
                ->show('mode == "manual"'),
        ];
    }
}
