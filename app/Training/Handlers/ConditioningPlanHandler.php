<?php

namespace App\Training\Handlers;

use App\Data\Exercise\ConditioningMode;
use App\Models\Exercise\Exercise;
use App\Training\Data\ExerciseOverrideData;
use App\Training\Data\GridDefinition;
use App\Training\Data\GridRow;
use App\Training\Data\GridWeekColumn;
use App\Training\Data\TrainingBlock;
use App\Training\Data\TrainingSet;
use App\Training\Strategies\StaticBlockStrategy;

class ConditioningPlanHandler extends AbstractExercisePlanHandler
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
        $typeConfig = $this->getExerciseTypeConfig($exercise);
        $isTimeMode = ($typeConfig?->mode ?? 'reps') === ConditioningMode::Time->value;

        if ($isTimeMode) {
            $setRows = [
                new GridRow(
                    field: 'duration',
                    color: 'bg-purple-50 dark:bg-purple-900/20',
                    overrideColor: 'bg-purple-200 dark:bg-purple-700/40',
                    editableMode: 'always',
                    inputType: 'number',
                    inputStep: '1',
                    suffix: 's',
                ),
            ];
        } else {
            $setRows = [
                new GridRow(
                    field: 'reps',
                    color: 'bg-blue-50 dark:bg-blue-900/20',
                    overrideColor: 'bg-blue-200 dark:bg-blue-700/40',
                    editableMode: 'always',
                    inputType: 'number',
                    inputStep: '1',
                ),
            ];
        }

        return new GridDefinition(
            setRows: $setRows,
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
        $mode = $typeConfig?->mode ?? 'reps';
        $isTimeMode = $mode === ConditioningMode::Time->value;

        $sets = $this->mergeConfigValue('sets', ExerciseOverrideData::DEFAULT_SETS, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
        $rest = $this->mergeConfigValue('rest', $typeConfig?->rest ?? ExerciseOverrideData::DEFAULT_REST, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);

        $result = [
            'mode' => $mode,
            'sets' => $sets['value'],
            'rest' => $rest['value'],
            'hasSetsOverride' => $sets['hasOverride'],
            'hasRestOverride' => $rest['hasOverride'],
        ];

        if ($isTimeMode) {
            $duration = $this->mergeConfigValue('duration', $typeConfig?->duration ?? 0, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
            $result['duration'] = $duration['value'];
            $result['hasDurationOverride'] = $duration['hasOverride'];
        } else {
            $reps = $this->mergeConfigValue('reps', $typeConfig?->reps ?? 10, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
            $result['reps'] = $reps['value'];
            $result['hasRepsOverride'] = $reps['hasOverride'];
        }

        return $result;
    }

    public function getConfigBadges(array $config): array
    {
        $isTimeMode = ($config['mode'] ?? 'reps') === ConditioningMode::Time->value;

        $badges = [];

        if ($isTimeMode) {
            $badges[] = ['field' => 'duration', 'label' => $config['duration'].'s', 'hasOverride' => $config['hasDurationOverride'] ?? false];
        } else {
            $badges[] = ['field' => 'reps', 'label' => $config['reps'].' reps', 'hasOverride' => $config['hasRepsOverride'] ?? false];
        }

        $badges[] = ['field' => 'sets', 'label' => $config['sets'].' sets', 'hasOverride' => $config['hasSetsOverride']];
        $badges[] = ['field' => 'rest', 'label' => $config['rest'].'s', 'hasOverride' => $config['hasRestOverride'] ?? false];

        return $badges;
    }

    public function getSettingsFields(Exercise $exercise): array
    {
        $typeConfig = $this->getExerciseTypeConfig($exercise);
        $isTimeMode = ($typeConfig?->mode ?? 'reps') === ConditioningMode::Time->value;

        $fields = [];

        if ($isTimeMode) {
            $fields[] = ['field' => 'duration', 'label' => 'Duration', 'type' => 'number', 'suffix' => 'seconds', 'min' => 0, 'step' => 1];
        } else {
            $fields[] = ['field' => 'reps', 'label' => 'Reps', 'type' => 'number', 'suffix' => 'reps', 'min' => 1, 'max' => 100, 'step' => 1];
        }

        $fields[] = ['field' => 'sets', 'label' => 'Sets', 'type' => 'number', 'suffix' => 'sets', 'min' => 1, 'max' => 10, 'step' => 1];
        $fields[] = ['field' => 'rest', 'label' => 'Rest', 'type' => 'number', 'suffix' => 'seconds', 'min' => 0, 'max' => 300, 'step' => 5];

        return $fields;
    }

    public function generateBlock(
        array $config,
        int $weeks,
        int $sessionsPerWeek,
        ?array $measuredData = null,
    ): ?TrainingBlock {
        $isTimeMode = ($config['mode'] ?? 'reps') === ConditioningMode::Time->value;
        $sets = (int) ($config['sets'] ?? ExerciseOverrideData::DEFAULT_SETS);

        $templateSet = $isTimeMode
            ? new TrainingSet(duration: (int) ($config['duration'] ?? 0))
            : new TrainingSet(reps: (int) ($config['reps'] ?? 10));

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
