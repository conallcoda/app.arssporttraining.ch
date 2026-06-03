<?php

namespace App\Data\Exercise\Preview;

use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Exercise\Strategies\AutomaticStrategyFactory;
use App\Data\Exercise\Strategies\Contracts\DefinesCellColors;
use App\Data\Exercise\Strategies\Contracts\DefinesEditability;
use App\Data\Exercise\Strategies\HeartRate\HeartRateZoneCellColors;
use App\Data\Exercise\Strategies\Sets\DeloadSetsStrategy;
use App\Support\Profiling\PlanGridProfiler;
use App\Support\Training\ApplyPerScope;

class StrategyOrchestrator
{
    public function __construct(
        private array $data,
        private ?WeightProgressionSetting $measuredData = null,
        private int $weeks = 5,
        private ?GridOverrides $overrides = null,
        private ?int $maxHR = null,
        private ?int $iatPercent = null,
        private ?AutomaticStrategyFactory $automaticStrategies = null,
        /** @var array<int, int> */
        private array $sessionCounts = [],
    ) {}

    public function execute(): GridState
    {
        $span = PlanGridProfiler::start('StrategyOrchestrator.execute', [
            'weeks' => $this->weeks,
            'settings' => $this->data['settings'] ?? [],
            'session_count_total' => array_sum($this->resolveSessionCounts()),
            'override_cells' => count($this->overrides?->cells ?? []),
            'override_sessions' => count($this->overrides?->sessions ?? []),
        ]);

        try {
            $state = new GridState;

            if ($this->overrides !== null) {
                $state->setOverrides($this->overrides);
            }

            PlanGridProfiler::measure('StrategyOrchestrator.execute.sets', [], fn () => $this->executeSetsPhase($state));
            PlanGridProfiler::measure('StrategyOrchestrator.execute.reps', [], fn () => $this->executeRepsPhase($state));
            PlanGridProfiler::measure('StrategyOrchestrator.execute.weight', [], fn () => $this->executeWeightPhase($state));
            PlanGridProfiler::measure('StrategyOrchestrator.execute.heartRateZone', [], fn () => $this->executeHeartRateZonePhase($state));
            PlanGridProfiler::measure('StrategyOrchestrator.execute.heartRate', [], fn () => $this->executeHeartRatePhase($state));

            return $state;
        } finally {
            PlanGridProfiler::end($span, [
                'max_sets' => isset($state) ? $state->maxSets() : null,
            ]);
        }
    }

    private function executeSetsPhase(GridState $state): void
    {
        $setsSetting = SetsSetting::from($this->data['sets'] ?? []);
        $preview = $this->data['preview'] ?? [];
        $strategy = new DeloadSetsStrategy(
            $setsSetting,
            groupingMode: (string) ($preview['groupingMode'] ?? SessionGroupingMode::defaultMode()),
            groupSize: SessionGroupingMode::normalizeGroupSize(
                (int) ($preview['groupSize'] ?? SessionGroupingMode::defaultGroupSize()),
                (string) ($preview['groupingMode'] ?? SessionGroupingMode::defaultMode()),
            ),
            sessionCounts: $this->resolveSessionCounts(),
        );
        $strategy->generate($this->weeks, $state);
        $this->registerEditability($strategy, $state);

        if ($this->overrides !== null) {
            $setsPerWeek = $state->getSetsPerWeek();

            foreach ($this->overrides->sessions as $override) {
                if (! isset($override->data['sets'])) {
                    continue;
                }

                $week = $override->week;

                if ($week < 0 || $week >= $this->weeks) {
                    continue;
                }

                $setsPerWeek[$week] = max(
                    (int) ($setsPerWeek[$week] ?? 0),
                    max(0, (int) $override->data['sets']),
                );
            }

            $state->setSetsPerWeek($setsPerWeek);
        }
    }

    private function executeRepsPhase(GridState $state): void
    {
        $settings = $this->data['settings'] ?? [];

        if (! in_array('reps', $settings)) {
            return;
        }

        $config = $this->data['reps'] ?? [];
        $mode = $config['mode'] ?? 'manual';
        $applyPer = ApplyPerScope::normalize($config['applyPer'] ?? null);

        if ($applyPer === ApplyPerScope::SESSION) {
            return;
        }

        if ($mode === 'automatic') {
            $strategy = $this->automaticStrategies()->makeRepsStrategy($config);

            if ($strategy === null) {
                return;
            }

            $strategy->generate(
                $this->weeks,
                $state,
                $this->resolveSessionCounts(),
                (string) ($this->data['preview']['groupingMode'] ?? SessionGroupingMode::defaultMode()),
                SessionGroupingMode::normalizeGroupSize(
                    (int) ($this->data['preview']['groupSize'] ?? SessionGroupingMode::defaultGroupSize()),
                    (string) ($this->data['preview']['groupingMode'] ?? SessionGroupingMode::defaultMode()),
                ),
            );
            $this->registerEditability($strategy, $state);

            return;
        }

        $rawDefault = $config['default'] ?? null;
        $requiresConcreteReps = RepsSetting::requiresConcretePlanningReps($this->data);
        $defaultReps = match (true) {
            $rawDefault === null || $rawDefault === '' => $requiresConcreteReps ? 10 : '-',
            is_string($rawDefault) && str_contains($rawDefault, '_') => $rawDefault,
            is_string($rawDefault) && str_contains($rawDefault, '-') => $rawDefault,
            default => (int) $rawDefault,
        };
        $state->setGrid('reps', $this->fillGrid($state, $defaultReps));
    }

    private function executeWeightPhase(GridState $state): void
    {
        $settings = $this->data['settings'] ?? [];

        if (! in_array('weight', $settings)) {
            return;
        }

        $config = $this->data['weight'] ?? [];
        $mode = $config['mode'] ?? 'manual';
        $applyPer = ApplyPerScope::normalize($config['applyPer'] ?? null);

        if ($applyPer === ApplyPerScope::SESSION || $mode !== 'automatic' || $this->measuredData === null) {
            return;
        }

        if (! $state->hasGrid('reps')) {
            return;
        }

        $strategy = $this->automaticStrategies()->makeWeightStrategy($config, $this->measuredData);

        if ($strategy === null) {
            return;
        }

        $strategy->generate(
            $this->weeks,
            $state,
            $this->resolveSessionCounts(),
            (string) ($this->data['preview']['groupingMode'] ?? SessionGroupingMode::defaultMode()),
            SessionGroupingMode::normalizeGroupSize(
                (int) ($this->data['preview']['groupSize'] ?? SessionGroupingMode::defaultGroupSize()),
                (string) ($this->data['preview']['groupingMode'] ?? SessionGroupingMode::defaultMode()),
            ),
        );
        $this->registerEditability($strategy, $state);
    }

    private function executeHeartRateZonePhase(GridState $state): void
    {
        $settings = $this->data['settings'] ?? [];

        if (! in_array('heartRateZone', $settings)) {
            return;
        }

        $config = $this->data['heartRateZone'] ?? [];
        $applyPer = ApplyPerScope::normalize($config['applyPer'] ?? null);

        $state->addCellColorStrategy(new HeartRateZoneCellColors);

        if ($applyPer === ApplyPerScope::SESSION) {
            return;
        }

        $defaultZone = $config['default'] ?? '2';
        $state->setGrid('heartRateZone', $this->fillGrid($state, $defaultZone));
    }

    private function executeHeartRatePhase(GridState $state): void
    {
        $settings = $this->data['settings'] ?? [];

        if (! in_array('heartRate', $settings)) {
            return;
        }

        $config = $this->data['heartRate'] ?? [];
        $mode = $config['mode'] ?? 'manual';

        if ($mode === 'manual') {
            return;
        }

        $applyPer = ApplyPerScope::normalize($config['applyPer'] ?? null);

        if ($applyPer === ApplyPerScope::SESSION) {
            return;
        }

        if (! $state->hasGrid('heartRateZone')) {
            $defaultZone = $this->data['heartRateZone']['default'] ?? '2';
            $state->setGrid('heartRateZone', $this->fillGrid($state, $defaultZone));
        }

        $strategy = $this->automaticStrategies()->makeHeartRateStrategy($config, $this->maxHR, $this->iatPercent);

        if ($strategy === null) {
            return;
        }

        $strategy->generate($this->weeks, $state);
        $this->registerEditability($strategy, $state);
    }

    private function automaticStrategies(): AutomaticStrategyFactory
    {
        return $this->automaticStrategies ??= new AutomaticStrategyFactory;
    }

    private function registerEditability(object $strategy, GridState $state): void
    {
        if ($strategy instanceof DefinesEditability) {
            $state->addEditabilityStrategy($strategy);
        }

        if ($strategy instanceof DefinesCellColors) {
            $state->addCellColorStrategy($strategy);
        }
    }

    /** @return array<int, array<int, mixed>> */
    private function fillGrid(GridState $state, mixed $value): array
    {
        $grid = [];
        $setsPerWeek = $state->getSetsPerWeek();

        for ($week = 0; $week < $this->weeks; $week++) {
            $grid[$week] = array_fill(0, $setsPerWeek[$week], $value);
        }

        return $grid;
    }

    /** @return array<int, int> */
    private function resolveSessionCounts(): array
    {
        if ($this->sessionCounts !== []) {
            return $this->sessionCounts;
        }

        $sessionsPerWeek = SessionGroupingMode::resolvePreviewSessionCount($this->data['preview'] ?? [], 1);

        return array_fill(0, $this->weeks, $sessionsPerWeek);
    }
}
