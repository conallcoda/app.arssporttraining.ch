<?php

namespace App\Data\Exercise\Preview;

use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Fields;

class SessionGroupingConfig extends AbstractData
{
    public function __construct(
        public ?string $mode = null,
        public ?int $groupSize = null,
        public ?bool $copyValuesAutomatically = null,
    ) {
        $this->mode = SessionGroupingMode::normalizeMode($this->mode);
        $this->groupSize = SessionGroupingMode::normalizeGroupSize($this->groupSize, $this->mode);
        $this->copyValuesAutomatically = SessionGroupingMode::normalizeCopyValuesAutomatically($this->copyValuesAutomatically, $this->mode);
    }

    public static function fields(array $data = []): array
    {
        $mode = SessionGroupingMode::normalizeMode((string) ($data['mode'] ?? null));

        return [
            Fields\RadioSegmented::make('mode')
                ->label('Session Grouping')
                ->options(SessionGroupingMode::options())
                ->default(SessionGroupingMode::defaultMode())
                ->live(),
            Fields\Number::make('groupSize')
                ->label(SessionGroupingMode::sizeFieldLabel($mode))
                ->min(SessionGroupingMode::sizeFieldMin($mode))
                ->max(SessionGroupingMode::sizeFieldMax($mode))
                ->step(1)
                ->default(SessionGroupingMode::defaultGroupSize($mode))
                ->suffix(SessionGroupingMode::sizeFieldSuffix($mode))
                ->show('mode != "none"'),
        ];
    }
}
