<?php

namespace App\Data\Exercise\Preview;

use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Fields;

class SessionGroupingConfig extends AbstractData
{
    public function __construct(
        public ?string $mode = null,
        public ?int $groupSize = null,
    ) {
        $this->mode = SessionGroupingMode::normalizeMode($this->mode);
        $this->groupSize = SessionGroupingMode::normalizeGroupSize($this->groupSize, $this->mode);
    }

    public static function fields(): array
    {
        return [
            Fields\RadioSegmented::make('mode')
                ->label('Session Grouping')
                ->options(SessionGroupingMode::options())
                ->default(SessionGroupingMode::defaultMode())
                ->live(),
            Fields\Number::make('groupSize')
                ->label('Group Size')
                ->min(1)
                ->max(12)
                ->step(1)
                ->default(SessionGroupingMode::defaultGroupSize())
                ->suffix('session(s)')
                ->show('mode == "groups"'),
        ];
    }
}
