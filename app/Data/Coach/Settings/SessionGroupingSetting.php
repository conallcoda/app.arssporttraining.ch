<?php

namespace App\Data\Coach\Settings;

use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Data\Exercise\Preview\SessionGroupingMode;

class SessionGroupingSetting extends AbstractCoachSetting
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
        return SessionGroupingConfig::fields($data);
    }
}
