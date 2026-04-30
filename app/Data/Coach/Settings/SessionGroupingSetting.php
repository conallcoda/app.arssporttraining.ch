<?php

namespace App\Data\Coach\Settings;

use App\Data\Exercise\Preview\SessionGroupingMode;
use Coda\FormKit\Fields;

class SessionGroupingSetting extends AbstractCoachSetting
{
    public function __construct(
        public string $mode = SessionGroupingMode::Week->value,
        public int $groupSize = 4,
    ) {
        $this->groupSize = max(1, $this->groupSize);
    }

    public static function fields(): array
    {
        return [
            Fields\RadioSegmented::make('mode')
                ->label('Session Grouping')
                ->options(SessionGroupingMode::options())
                ->default(SessionGroupingMode::Week->value)
                ->live(),
            Fields\Number::make('groupSize')
                ->label('Group Size')
                ->min(1)
                ->max(12)
                ->step(1)
                ->default(4)
                ->suffix('session(s)')
                ->show('mode == "groups"'),
        ];
    }
}
