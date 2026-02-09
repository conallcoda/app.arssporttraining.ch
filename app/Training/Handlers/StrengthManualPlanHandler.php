<?php

namespace App\Training\Handlers;

use App\Models\Exercise\Exercise;
use App\Training\Data\ExerciseOverrideData;
use App\Training\Data\GridDefinition;
use App\Training\Data\GridRow;
use App\Training\Data\GridWeekColumn;
use App\Training\Data\TrainingBlock;
use App\Training\Data\TrainingSession;
use App\Training\Data\TrainingSet;
use App\Training\Data\TrainingWeek;
use App\Training\Services\DeloadRule;
use App\Training\Services\PairedRepCalculator;

class StrengthManualPlanHandler extends AbstractExercisePlanHandler
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
                    editableMode: 'always',
                    inputType: 'number',
                    inputStep: '0.5',
                ),
            ],
            weekColumns: [
                new GridWeekColumn(
                    field: 'tut',
                    label: 'TUT',
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

        $startingWeight = $this->mergeConfigValue('startingWeight', $typeConfig?->startingWeight ?? 0, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
        $startingReps = $this->mergeConfigValue('startingReps', $typeConfig?->startingReps ?? ExerciseOverrideData::DEFAULT_STARTING_REPS, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
        $sets = $this->mergeConfigValue('sets', ExerciseOverrideData::DEFAULT_SETS, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
        $tut = $this->mergeConfigValue('tut', $typeConfig?->timeUnderTension ?? ExerciseOverrideData::DEFAULT_TUT, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);
        $rest = $this->mergeConfigValue('rest', $typeConfig?->rest ?? ExerciseOverrideData::DEFAULT_REST, $pivotConfig, $defaultOverrides, $userOverrides, $isDefaultUser);

        return [
            'startingWeight' => $startingWeight['value'],
            'startingReps' => $startingReps['value'],
            'sets' => $sets['value'],
            'tut' => $tut['value'],
            'rest' => $rest['value'],
            'hasStartingWeightOverride' => $startingWeight['hasOverride'],
            'hasStartingRepsOverride' => $startingReps['hasOverride'],
            'hasSetsOverride' => $sets['hasOverride'],
            'hasTutOverride' => $tut['hasOverride'],
            'hasRestOverride' => $rest['hasOverride'],
        ];
    }

    public function getConfigBadges(array $config): array
    {
        return [
            ['field' => 'startingWeight', 'label' => $config['startingWeight'].'kg', 'hasOverride' => $config['hasStartingWeightOverride']],
            ['field' => 'startingReps', 'label' => $config['startingReps'].' reps', 'hasOverride' => $config['hasStartingRepsOverride']],
            ['field' => 'sets', 'label' => $config['sets'].' sets', 'hasOverride' => $config['hasSetsOverride']],
            ['field' => 'tut', 'label' => $config['tut'], 'hasOverride' => $config['hasTutOverride'] ?? false],
            ['field' => 'rest', 'label' => $config['rest'].'s', 'hasOverride' => $config['hasRestOverride'] ?? false],
        ];
    }

    public function getSettingsFields(Exercise $exercise): array
    {
        return [
            ['field' => 'startingWeight', 'label' => 'Starting Weight', 'type' => 'number', 'suffix' => 'kg', 'min' => 0, 'step' => 0.5],
            ['field' => 'startingReps', 'label' => 'Starting Reps', 'type' => 'number', 'suffix' => 'reps', 'min' => 1, 'max' => 25, 'step' => 1],
            ['field' => 'sets', 'label' => 'Sets', 'type' => 'number', 'suffix' => 'sets', 'min' => 1, 'max' => 6, 'step' => 1],
            ['field' => 'tut', 'label' => 'Time Under Tension', 'type' => 'text', 'maxlength' => 4, 'placeholder' => '3010', 'description' => '4 digits: eccentric, pause, concentric, pause'],
            ['field' => 'rest', 'label' => 'Rest', 'type' => 'number', 'suffix' => 'seconds', 'min' => 0, 'max' => 300, 'step' => 5],
        ];
    }

    public function generateBlock(
        array $config,
        int $weeks,
        int $sessionsPerWeek,
        ?array $measuredData = null,
    ): ?TrainingBlock {
        $startingWeight = (float) ($config['startingWeight'] ?? 0);
        $startingReps = (int) ($config['startingReps'] ?? ExerciseOverrideData::DEFAULT_STARTING_REPS);
        $sets = (int) ($config['sets'] ?? ExerciseOverrideData::DEFAULT_SETS);

        $repCalculator = new PairedRepCalculator(
            startingReps: $startingReps,
        );

        $deloadRule = new DeloadRule(setsToReduceBy: 1);

        $trainingWeeks = [];

        for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {
            $actualSets = $deloadRule->getSetsForWeek($weekIndex, $sets);

            $sessions = [];

            for ($sessionIndex = 0; $sessionIndex < $sessionsPerWeek; $sessionIndex++) {
                $traingSets = [];
                for ($setIndex = 0; $setIndex < $actualSets; $setIndex++) {
                    $reps = $repCalculator->getRepsForSet($weekIndex, $setIndex);
                    $traingSets[] = new TrainingSet(
                        reps: $reps,
                        weight: $startingWeight,
                    );
                }
                $sessions[] = new TrainingSession(sets: $traingSets);
            }

            $trainingWeeks[] = new TrainingWeek(sessions: $sessions);
        }

        $exerciseConfig = $this->buildMinimalExerciseConfig($sets, $sessionsPerWeek);

        return new TrainingBlock(
            config: $exerciseConfig,
            weeks: $trainingWeeks,
        );
    }

    public function getHeaderInfo(?TrainingBlock $block, array $config): ?array
    {
        $startingWeight = $config['startingWeight'] ?? 0;

        if ($startingWeight <= 0) {
            return null;
        }

        return [
            'type' => 'strength_manual',
            'startingWeight' => $startingWeight,
        ];
    }

    public function getDefaultWeekValues(array $config): array
    {
        return [
            'tut' => $config['tut'] ?? ExerciseOverrideData::DEFAULT_TUT,
            'rest' => $config['rest'] ?? ExerciseOverrideData::DEFAULT_REST,
        ];
    }
}
