<?php

namespace App\Data\Exercise\Strategies\Sets;

use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Preview\SessionGroupBuilder;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Exercise\Strategies\Contracts\DefinesEditability;

class DeloadSetsStrategy implements DefinesEditability
{
    public function __construct(
        private SetsSetting $setting,
        private ?string $groupingMode = null,
        private ?int $groupSize = null,
        /** @var array<int, int> */
        private array $sessionCounts = [],
    ) {
        $this->groupingMode = SessionGroupingMode::tryFrom((string) $this->groupingMode)?->value ?? SessionGroupingMode::defaultMode();
        $this->groupSize = max(1, (int) ($this->groupSize ?? SessionGroupingMode::defaultGroupSize()));
    }

    /** @return array<int, int> */
    public function generate(int $weeks, GridState $state): array
    {
        $setsPerWeek = [];
        $sessionSetGrid = [];

        $strategyMap = SessionGroupBuilder::buildStrategyMap(
            weekCount: $weeks,
            sessionCounts: $this->sessionCounts,
            groupingMode: $this->groupingMode,
            groupSize: $this->groupSize,
        );

        foreach ($strategyMap['orderedSessions'] as $session) {
            $week = $session['week'];
            $sessionIndex = $session['session'];
            $sets = $this->getSetsForGroup($session['group']);

            $sessionSetGrid[$week][$sessionIndex][0] = $sets;
            $setsPerWeek[$week] = max($setsPerWeek[$week] ?? 0, $sets);
        }

        for ($week = 0; $week < $weeks; $week++) {
            $setsPerWeek[$week] ??= $this->getSetsForGroup($week);
        }

        $state->setSetsPerWeek($setsPerWeek);
        $state->setSessionGrid('sets', $sessionSetGrid);

        return $setsPerWeek;
    }

    public function isEditable(string $field, int $week, int $set, GridState $state): bool
    {
        return $set < ($state->getSetsPerWeek()[$week] ?? 0);
    }

    public function getSetsForWeek(int $weekIndex): int
    {
        return $this->getSetsForGroup($weekIndex);
    }

    public function getSetsForGroup(int $groupIndex): int
    {
        if ($this->setting->deload === 'none') {
            return $this->setting->default;
        }

        $groupNumber = $groupIndex + 1;

        $isDeloadGroup = match ($this->setting->deload) {
            'odd' => $groupNumber % 2 === 1,
            'even' => $groupNumber % 2 === 0,
            default => false,
        };

        if ($isDeloadGroup) {
            return max(0, $this->setting->default - ($this->setting->deloadBy ?? 1));
        }

        return $this->setting->default;
    }
}
