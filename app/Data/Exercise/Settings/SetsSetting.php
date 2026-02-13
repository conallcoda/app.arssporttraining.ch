<?php

namespace App\Data\Exercise\Settings;

use App\Cms\Form\Fields;

class SetsSetting extends AbstractSetting
{
    public function __construct(
        public string $deload = 'odd',
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
                ->live()
                ->show('deload != "none"'),
            Fields\Text::make('label')
                ->label('Label')
                ->default('Set')
                ->live()
                ->show('deload != "none"')
                ->suffix('set(s)'),
            Fields\Sets::make('default')
                ->label('Default')
                ->live(),
        ];
    }
}
