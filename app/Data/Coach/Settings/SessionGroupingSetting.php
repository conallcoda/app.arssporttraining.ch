<?php

namespace App\Data\Coach\Settings;

use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Data\Exercise\Preview\SessionGroupingMode;

class SessionGroupingSetting extends AbstractCoachSetting
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
        return SessionGroupingConfig::fields();
    }
}
