<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Form\Fields;

class RestSetting extends AbstractSetting
{
    public function __construct(
        public int $default = 60,
        public string $applyPer = 'week',
    ) {}

    public static function unitLabel(): string
    {
        return 's';
    }
    public static function fields(): array
    {
        return [
            Fields\Number::make('default')
                ->label('Default Rest')
                ->default(60)
                ->min(0)
                ->suffix('sec')
                ->live(),
            ApplyPerField::make('week'),
        ];
    }
}
