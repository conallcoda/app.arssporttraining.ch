<?php

namespace App\Training\Handlers;

use App\Models\Exercise\Exercise;
use App\Training\Data\ExerciseOverrideData;
use App\Training\Data\GridDefinition;
use App\Training\Data\GridRow;
use App\Training\Data\GridWeekColumn;
use App\Training\Data\TrainingBlock;
use App\Training\Data\TrainingSet;
use App\Training\Strategies\StaticBlockStrategy;

class TimedPlanHandler extends AbstractExercisePlanHandler
{
    public function needsMeasuredData(): bool
    {
        return false;
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
                    field: 'duration',
                    color: 'bg-purple-50 dark:bg-purple-900/20',
                    overrideColor: 'bg-purple-200 dark:bg-purple-700/40',
                    editableMode: 'always',
                    inputType: 'number',
                    inputStep: '1',
                    suffix: 's',
                ),
            ],
            weekColumns: [
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

        $duration = $this->mergeConfigValue('duration', $typeConfig?->duration ?? 0, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
        $sets = $this->mergeConfigValue('sets', ExerciseOverrideData::DEFAULT_SETS, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
        $rest = $this->mergeConfigValue('rest', $typeConfig?->rest ?? ExerciseOverrideData::DEFAULT_REST, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);

        return [
            'duration' => $duration['value'],
            'sets' => $sets['value'],
            'rest' => $rest['value'],
            'hasDurationOverride' => $duration['hasOverride'],
            'hasSetsOverride' => $sets['hasOverride'],
            'hasRestOverride' => $rest['hasOverride'],
        ];
    }

    public function getConfigBadges(array $config): array
    {
        return [
            ['field' => 'duration', 'label' => $config['duration'].'s', 'hasOverride' => $config['hasDurationOverride'] ?? false],
            ['field' => 'sets', 'label' => $config['sets'].' sets', 'hasOverride' => $config['hasSetsOverride']],
            ['field' => 'rest', 'label' => $config['rest'].'s', 'hasOverride' => $config['hasRestOverride'] ?? false],
        ];
    }

    public function getSettingsFields(Exercise $exercise): array
    {
        return [
            ['field' => 'duration', 'label' => 'Duration', 'type' => 'number', 'suffix' => 'seconds', 'min' => 0, 'step' => 1],
            ['field' => 'sets', 'label' => 'Sets', 'type' => 'number', 'suffix' => 'sets', 'min' => 1, 'max' => 10, 'step' => 1],
            ['field' => 'rest', 'label' => 'Rest', 'type' => 'number', 'suffix' => 'seconds', 'min' => 0, 'max' => 300, 'step' => 5],
        ];
    }

    public function generateBlock(
        array $config,
        int $weeks,
        int $sessionsPerWeek,
        ?array $measuredData = null,
    ): ?TrainingBlock {
        $sets = (int) ($config['sets'] ?? ExerciseOverrideData::DEFAULT_SETS);

        $templateSet = new TrainingSet(
            duration: (int) ($config['duration'] ?? 0),
        );

        $exerciseConfig = $this->buildMinimalExerciseConfig($sets, $sessionsPerWeek);

        $strategy = new StaticBlockStrategy(
            templateSet: $templateSet,
            sets: $sets,
        );

        return $strategy->generate($exerciseConfig, $weeks);
    }

    public function getHeaderInfo(?TrainingBlock $block, array $config): ?array
    {
        return null;
    }

    public function getDefaultWeekValues(array $config): array
    {
        return [
            'rest' => $config['rest'] ?? ExerciseOverrideData::DEFAULT_REST,
        ];
    }
}
