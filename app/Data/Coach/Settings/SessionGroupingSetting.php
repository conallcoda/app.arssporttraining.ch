<?php

namespace App\Data\Coach\Settings;

use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Data\Exercise\Preview\SessionGroupingMode;
use Coda\FormKit\Fields\SwitchField;

class SessionGroupingSetting extends AbstractCoachSetting
{
    public function __construct(
        public ?string $mode = null,
        public ?int $groupSize = null,
        public ?bool $copyValuesAutomatically = null,
        public ?bool $showDatePerSession = null,
        public ?bool $showGroupedColumn = null,
    ) {
        $this->mode = SessionGroupingMode::normalizeMode($this->mode);
        $this->groupSize = SessionGroupingMode::normalizeGroupSize($this->groupSize, $this->mode);
        $this->copyValuesAutomatically = SessionGroupingMode::normalizeCopyValuesAutomatically($this->copyValuesAutomatically, $this->mode);
        $this->showDatePerSession ??= false;
        $this->showGroupedColumn ??= true;
    }

    public static function fields(array $data = []): array
    {
        return [
            ...SessionGroupingConfig::fields($data),
            SwitchField::make('showDatePerSession')
                ->label('Show date per session')
                ->default(false),
            SwitchField::make('showGroupedColumn')
                ->label('Show grouped column')
                ->default(true)
                ->show('mode != "none"'),
        ];
    }
}
