<?php

namespace App\Training\Handlers;

use App\Models\Exercise\Exercise;
use App\Training\Data\ExerciseOverrideData;
use App\Training\Data\GridDefinition;
use App\Training\Data\GridRow;
use App\Training\Data\GridWeekColumn;
use App\Training\Data\TrainingBlock;
use App\Training\Services\TrainingBlockGenerator;

class StrengthAutomaticPlanHandler extends AbstractExercisePlanHandler
{
    public function needsMeasuredData(): bool
    {
        return true;
    }

    public function hasSets(): bool
    {
        return true;
    }

    public function getGridDefinition(Exercise $exercise): GridDefinition
    {
        return new GridDefinition(
            setRows: [
                new GridRow(
                    field: 'reps',
                    color: 'bg-blue-50 dark:bg-blue-900/20',
                    overrideColor: 'bg-blue-200 dark:bg-blue-700/40',
                    editableMode: 'always',
                    inputType: 'number',
                    inputStep: '1',
                ),
                new GridRow(
                    field: 'weight',
                    color: 'bg-green-50 dark:bg-green-900/20',
                    overrideColor: 'bg-green-200 dark:bg-green-700/40',
                    editableMode: 'user-only',
                    inputType: 'number',
                    inputStep: '0.5',
                ),
                new GridRow(
                    field: 'oneRepMax',
                    color: 'bg-orange-50 dark:bg-orange-900/20',
                    overrideColor: 'bg-orange-50 dark:bg-orange-900/20',
                    editableMode: 'never',
                ),
            ],
            weekColumns: [
                new GridWeekColumn(
                    field: 'tempo',
                    label: 'Tempo',
                    color: 'bg-zinc-50 dark:bg-zinc-800/50',
                    overrideColor: 'bg-zinc-200 dark:bg-zinc-700/70',
                    inputType: 'text',
                ),
                new GridWeekColumn(
                    field: 'rest',
                    label: 'Rest',
                    color: 'bg-zinc-50 dark:bg-zinc-800/50',
                    overrideColor: 'bg-zinc-200 dark:bg-zinc-700/70',
                    inputType: 'number',
                    inputStep: '5',
                    suffix: 's',
                ),
            ],
        );
    }

    public function resolveConfig(
        Exercise $exercise,
        array $pivotConfig,
        array $defaultOverrides,
        array $userOverrides,
        bool $isDefaultUser,
    ): array {
        $typeConfig = $this->getExerciseTypeConfig($exercise);

        $target = $this->mergeConfigValue('target', ExerciseOverrideData::DEFAULT_TARGET, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
        $startingReps = $this->mergeConfigValue('startingReps', $typeConfig?->startingReps ?? ExerciseOverrideData::DEFAULT_STARTING_REPS, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
        $sets = $this->mergeConfigValue('sets', ExerciseOverrideData::DEFAULT_SETS, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
        $tempo = $this->mergeConfigValue('tempo', $typeConfig?->tempo ?? ExerciseOverrideData::DEFAULT_TEMPO, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
        $rest = $this->mergeConfigValue('rest', $typeConfig?->rest ?? ExerciseOverrideData::DEFAULT_REST, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);

        $oneRepMaxModifier = $pivotConfig['oneRepMaxModifier'] ?? $typeConfig?->oneRepMaxModifier ?? 100;

        return [
            'target' => $target['value'],
            'startingReps' => $startingReps['value'],
            'sets' => $sets['value'],
            'tempo' => $tempo['value'],
            'rest' => $rest['value'],
            'oneRepMaxModifier' => $oneRepMaxModifier,
            'hasTargetOverride' => $target['hasOverride'],
            'hasStartingRepsOverride' => $startingReps['hasOverride'],
            'hasSetsOverride' => $sets['hasOverride'],
            'hasTempoOverride' => $tempo['hasOverride'],
            'hasRestOverride' => $rest['hasOverride'],
        ];
    }

    public function getConfigBadges(array $config): array
    {
        return [
            ['field' => 'target', 'label' => '+'.$config['target'].'%', 'hasOverride' => $config['hasTargetOverride']],
            ['field' => 'startingReps', 'label' => $config['startingReps'].' reps', 'hasOverride' => $config['hasStartingRepsOverride']],
            ['field' => 'sets', 'label' => $config['sets'].' sets', 'hasOverride' => $config['hasSetsOverride']],
            ['field' => 'tempo', 'label' => $config['tempo'], 'hasOverride' => $config['hasTempoOverride'] ?? false],
            ['field' => 'rest', 'label' => $config['rest'].'s', 'hasOverride' => $config['hasRestOverride'] ?? false],
        ];
    }

    public function getSettingsFields(Exercise $exercise): array
    {
        return [
            ['field' => 'target', 'label' => 'Target', 'type' => 'number', 'suffix' => '%', 'min' => 0, 'step' => 0.5],
            ['field' => 'startingReps', 'label' => 'Starting Reps', 'type' => 'number', 'suffix' => 'reps', 'min' => 1, 'max' => 25, 'step' => 1],
            ['field' => 'sets', 'label' => 'Sets', 'type' => 'number', 'suffix' => 'sets', 'min' => 1, 'max' => 6, 'step' => 1],
            ['field' => 'tempo', 'label' => 'Tempo', 'type' => 'text', 'maxlength' => 4, 'placeholder' => '3010', 'description' => '4 digits: eccentric, pause, concentric, pause'],
            ['field' => 'rest', 'label' => 'Rest', 'type' => 'number', 'suffix' => 'seconds', 'min' => 0, 'max' => 300, 'step' => 5],
        ];
    }

    public function generateBlock(
        array $config,
        int $weeks,
        int $sessionsPerWeek,
        ?array $measuredData = null,
    ): ?TrainingBlock {
        if ($measuredData === null) {
            return null;
        }

        $measuredWeight = $measuredData['measuredWeight'] ?? null;
        $measuredReps = $measuredData['measuredReps'] ?? null;

        if ($measuredWeight === null || $measuredWeight <= 0 || $measuredReps === null || $measuredReps < 1) {
            return null;
        }

        $generator = new TrainingBlockGenerator;

        return $generator->generate(
            measuredWeight: $measuredWeight,
            measuredReps: $measuredReps,
            oneRepMaxModifier: $config['oneRepMaxModifier'],
            targetPercentage: $config['target'],
            startingReps: $config['startingReps'],
            sets: $config['sets'],
            weeks: $weeks,
            sessionsPerWeek: $sessionsPerWeek,
            deloadEnabled: true,
            deloadSetsReduction: 1,
        );
    }

    public function getHeaderInfo(?TrainingBlock $block, array $config): ?array
    {
        if (! $block) {
            return null;
        }

        $modifier = $config['oneRepMaxModifier'] ?? 100;

        return [
            'type' => 'strength_automatic',
            'starting1RM' => $block->config->starting1RM,
            'target1RM' => $block->config->target1RM,
            'modifierDiff' => $modifier - 100,
            'targetPercent' => $config['target'] ?? 0,
        ];
    }

    public function getDefaultWeekValues(array $config): array
    {
        return [
            'tempo' => $config['tempo'] ?? ExerciseOverrideData::DEFAULT_TEMPO,
            'rest' => $config['rest'] ?? ExerciseOverrideData::DEFAULT_REST,
        ];
    }
}
