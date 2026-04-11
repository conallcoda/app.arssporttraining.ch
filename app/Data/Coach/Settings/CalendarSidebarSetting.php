<?php

namespace App\Data\Coach\Settings;

use Coda\FormKit\Fields\SwitchField;

class CalendarSidebarSetting extends AbstractCoachSetting
{
    public function __construct(
        public bool $enabled = true,
    ) {}

    public static function fields(): array
    {
        return [
            SwitchField::make('enabled')
                ->label('Show sidebar on calendar page')
                ->default(true),
        ];
    }
}
