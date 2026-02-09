<?php

namespace App\Training\Handlers;

use App\Data\Exercise\CardioMode;
use App\Models\Exercise\Exercise;
use App\Training\Data\GridDefinition;
use App\Training\Data\GridRow;
use App\Training\Data\TrainingBlock;
use App\Training\Data\TrainingSet;
use App\Training\Strategies\StaticBlockStrategy;

class CardioPlanHandler extends AbstractExercisePlanHandler
{
    public function needsMeasuredData(): bool
    {
        return false;
    }

    public function hasSets(): bool
    {
        return false;
    }

    public function getGridDefinition(Exercise $exercise): GridDefinition
    {
        $typeConfig = $this->getExerciseTypeConfig($exercise);
        $isTimeMode = ($typeConfig?->mode ?? 'distance') === CardioMode::Time->value;

        $setRows = [];

        if ($isTimeMode) {
            $setRows[] = new GridRow(
                field: 'duration',
                color: 'bg-purple-50 dark:bg-purple-900/20',
                overrideColor: 'bg-purple-200 dark:bg-purple-700/40',
                editableMode: 'always',
                inputType: 'number',
                inputStep: '1',
                suffix: 's',
            );
        } else {
            $setRows[] = new GridRow(
                field: 'distance',
                color: 'bg-teal-50 dark:bg-teal-900/20',
                overrideColor: 'bg-teal-200 dark:bg-teal-700/40',
                editableMode: 'always',
                inputType: 'number',
                inputStep: '1',
                suffix: 'm',
            );
        }

        $setRows[] = new GridRow(
            field: 'pace',
            color: 'bg-indigo-50 dark:bg-indigo-900/20',
            overrideColor: 'bg-indigo-200 dark:bg-indigo-700/40',
            editableMode: 'always',
            inputType: 'text',
        );

        $setRows[] = new GridRow(
            field: 'watts',
            color: 'bg-amber-50 dark:bg-amber-900/20',
            overrideColor: 'bg-amber-200 dark:bg-amber-700/40',
            editableMode: 'always',
            inputType: 'number',
            inputStep: '1',
            suffix: 'W',
        );

        return new GridDefinition(
            setRows: $setRows,
            weekColumns: [],
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
        $mode = $typeConfig?->mode ?? 'distance';
        $isTimeMode = $mode === CardioMode::Time->value;

        $pace = $this->mergeConfigValue('pace', $typeConfig?->pace ?? '00:00', $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
        $watts = $this->mergeConfigValue('watts', $typeConfig?->watts ?? 0, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);

        $result = [
            'mode' => $mode,
            'pace' => $pace['value'],
            'watts' => $watts['value'],
            'hasPaceOverride' => $pace['hasOverride'],
            'hasWattsOverride' => $watts['hasOverride'],
        ];

        if ($isTimeMode) {
            $duration = $this->mergeConfigValue('duration', $typeConfig?->duration ?? 0, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
            $result['duration'] = $duration['value'];
            $result['hasDurationOverride'] = $duration['hasOverride'];
        } else {
            $distance = $this->mergeConfigValue('distance', $typeConfig?->distance ?? 0, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
            $result['distance'] = $distance['value'];
            $result['hasDistanceOverride'] = $distance['hasOverride'];
        }

        return $result;
    }

    public function getConfigBadges(array $config): array
    {
        $isTimeMode = ($config['mode'] ?? 'distance') === CardioMode::Time->value;

        $badges = [];

        if ($isTimeMode) {
            $badges[] = ['field' => 'duration', 'label' => $config['duration'].'s', 'hasOverride' => $config['hasDurationOverride'] ?? false];
        } else {
            $badges[] = ['field' => 'distance', 'label' => $config['distance'].'m', 'hasOverride' => $config['hasDistanceOverride'] ?? false];
        }

        $badges[] = ['field' => 'pace', 'label' => ($config['pace'] ?? '00:00').' min/km', 'hasOverride' => $config['hasPaceOverride'] ?? false];
        $badges[] = ['field' => 'watts', 'label' => $config['watts'].'W', 'hasOverride' => $config['hasWattsOverride'] ?? false];

        return $badges;
    }

    public function getSettingsFields(Exercise $exercise): array
    {
        $typeConfig = $this->getExerciseTypeConfig($exercise);
        $isTimeMode = ($typeConfig?->mode ?? 'distance') === CardioMode::Time->value;

        $fields = [];

        if ($isTimeMode) {
            $fields[] = ['field' => 'duration', 'label' => 'Duration', 'type' => 'number', 'suffix' => 'seconds', 'min' => 0, 'step' => 1];
        } else {
            $fields[] = ['field' => 'distance', 'label' => 'Distance', 'type' => 'number', 'suffix' => 'meters', 'min' => 0, 'step' => 1];
        }

        $fields[] = ['field' => 'pace', 'label' => 'Pace', 'type' => 'text', 'placeholder' => '00:00'];
        $fields[] = ['field' => 'watts', 'label' => 'Watts', 'type' => 'number', 'suffix' => 'W', 'min' => 0, 'step' => 1];

        return $fields;
    }

    public function generateBlock(
        array $config,
        int $weeks,
        int $sessionsPerWeek,
        ?array $measuredData = null,
    ): ?TrainingBlock {
        $isTimeMode = ($config['mode'] ?? 'distance') === CardioMode::Time->value;

        $templateSet = $isTimeMode
            ? new TrainingSet(
                duration: (int) ($config['duration'] ?? 0),
                pace: $config['pace'] ?? '00:00',
                watts: (int) ($config['watts'] ?? 0),
            )
            : new TrainingSet(
                distance: (int) ($config['distance'] ?? 0),
                pace: $config['pace'] ?? '00:00',
                watts: (int) ($config['watts'] ?? 0),
            );

        $exerciseConfig = $this->buildMinimalExerciseConfig(1, $sessionsPerWeek);

        $strategy = new StaticBlockStrategy(
            templateSet: $templateSet,
            sets: 1,
        );

        return $strategy->generate($exerciseConfig, $weeks);
    }

    public function getHeaderInfo(?TrainingBlock $block, array $config): ?array
    {
        return null;
    }

    public function getDefaultWeekValues(array $config): array
    {
        return [];
    }
}
