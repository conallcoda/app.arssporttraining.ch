<?php

namespace App\Data\Exercise\Settings;

use Coda\Cms\Form\Fields;

class SetsSetting extends AbstractSetting
{
    public function __construct(
        public string $deload = 'none',
        public int $deloadBy = 1,
        public string $label = 'Set',
        public int $default = 4,
    ) {}

    public static function fields(): array
    {
        return [
            Fields\RadioSegmented::make('deload')
                ->label('Deload')
                ->options([
                    'odd' => 'Odd Weeks',
                    'even' => 'Even Weeks',
                    'none' => 'No Deload',
                ])
                ->default('odd')
                ->live(),
            Fields\Sets::make('deloadBy')
                ->label('Deload By')
                ->min(1)
                ->default(1)
                ->show('deload != "none"'),
            Fields\Text::make('label')
                ->label('Label')
                ->default('Set')
                ->suffix('set(s)'),
            Fields\Sets::make('default')
                ->label('Default'),
        ];
    }
}
