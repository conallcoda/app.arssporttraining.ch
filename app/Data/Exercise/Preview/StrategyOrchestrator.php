<?php

namespace App\Data\Exercise\Preview;

use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Exercise\Settings\WeightSetting;
use App\Data\Exercise\Strategies\Reps\PairedRepStrategy;
use App\Data\Exercise\Strategies\Sets\DeloadSetsStrategy;
use App\Data\Exercise\Strategies\Weight\OneRepMaxFixedStrategy;

class StrategyOrchestrator
{
    public function __construct(
        private array $data,
        private ?WeightProgressionSetting $measuredData = null,
        private int $weeks = 5,
        private ?GridOverrides $overrides = null,
    ) {}

    public function execute(): GridState
    {
        $state = new GridState;

        if ($this->overrides !== null) {
            $state->setOverrides($this->overrides);
        }

        $this->executeSetsPhase($state);
        $this->executeRepsPhase($state);
        $this->executeWeightPhase($state);

        return $state;
    }

    private function executeSetsPhase(GridState $state): void
    {
        $setsSetting = SetsSetting::from($this->data['sets'] ?? []);
        $strategy = new DeloadSetsStrategy($setsSetting);
        $strategy->generate($this->weeks, $state);
        $this->registerEditability($strategy, $state);
    }

    private function executeRepsPhase(GridState $state): void
    {
        $settings = $this->data['settings'] ?? [];

        if (! in_array('reps', $settings)) {
            return;
        }

        $config = $this->data['reps'] ?? [];
        $mode = $config['mode'] ?? 'manual';
        $applyPer = $config['applyPer'] ?? 'session';

        if ($applyPer === 'week') {
            return;
        }

        if ($mode === 'automatic') {
            $repsSetting = RepsSetting::from($config);
            $strategy = new PairedRepStrategy($repsSetting);
            $strategy->generate($this->weeks, $state);
            $this->registerEditability($strategy, $state);

            return;
        }

        $defaultReps = (int) ($config['default'] ?? 10);
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
        $applyPer = $config['applyPer'] ?? 'session';

        if ($applyPer === 'week' || $mode !== 'automatic' || $this->measuredData === null) {
            return;
        }

        if (! $state->hasGrid('reps')) {
            return;
        }

        $weightSetting = WeightSetting::from($config);
        $strategy = new OneRepMaxFixedStrategy($weightSetting, $this->measuredData);
        $strategy->generate($this->weeks, $state);
        $this->registerEditability($strategy, $state);
    }

    private function registerEditability(object $strategy, GridState $state): void
    {
        if ($strategy instanceof DefinesEditability) {
            $state->addEditabilityStrategy($strategy);
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
}
