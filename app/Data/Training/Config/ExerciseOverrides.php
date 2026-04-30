<?php

namespace App\Data\Training\Config;

use App\Data\Exercise\Settings\DistanceSetting;
use App\Data\Exercise\Settings\DurationSetting;
use App\Data\Exercise\Settings\HeartRateSetting;
use App\Data\Exercise\Settings\HeartRateZoneSetting;
use App\Data\Exercise\Settings\PaceSetting;
use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Settings\RestSetting;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Exercise\Settings\TempoSetting;
use App\Data\Exercise\Settings\WattsSetting;
use App\Data\Exercise\Settings\WeightSetting;
use App\Support\Training\GridOverrideNormalizer;
use Coda\Cms\Data\AbstractData;

class ExerciseOverrides extends AbstractData
{
    public function __construct(
        /** @var string[]|null */
        public ?array $settings = null,
        public ?string $startsAtDate = null,
        public ?SetsSetting $sets = null,
        public ?RepsSetting $reps = null,
        public ?WeightSetting $weight = null,
        public ?TempoSetting $tempo = null,
        public ?RestSetting $rest = null,
        public ?DistanceSetting $distance = null,
        public ?DurationSetting $duration = null,
        public ?HeartRateSetting $heartRate = null,
        public ?HeartRateZoneSetting $heartRateZone = null,
        public ?PaceSetting $pace = null,
        public ?WattsSetting $watts = null,
        /** @var array{sessions: array, cells: array} */
        public array $gridOverrides = ['sessions' => [], 'cells' => []],
        /** @var array{sessions: array, cells: array} */
        public array $historicalGridOverrides = ['sessions' => [], 'cells' => []],
        /** @var array{sessions: array, cells: array}|null */
        public ?array $baselineGridOverrides = null,
        public ?bool $disabled = null,
    ) {
        $this->gridOverrides = GridOverrideNormalizer::normalize($this->gridOverrides);
        $this->historicalGridOverrides = GridOverrideNormalizer::normalize($this->historicalGridOverrides);
        $this->baselineGridOverrides = $this->baselineGridOverrides === null
            ? null
            : GridOverrideNormalizer::normalize($this->baselineGridOverrides);
    }

    public function hasSettingOverride(string $setting): bool
    {
        return property_exists($this, $setting) && $this->{$setting} !== null;
    }

    public function hasAnyGridOverrides(): bool
    {
        return ! empty($this->gridOverrides['cells']) || ! empty($this->gridOverrides['sessions']);
    }
}
