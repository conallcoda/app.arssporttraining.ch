<?php

namespace App\Training\Handlers;

use App\Models\Exercise\Exercise;

abstract class AbstractExercisePlanHandler implements ExercisePlanHandlerInterface
{
    protected function mergeConfigValue(
        string $field,
        mixed $systemDefault,
        array $pivotConfig,
        array $defaultOverrides,
        array $userOverrides,
        bool $isDefaultUser,
    ): array {
        $systemValue = $pivotConfig[$field] ?? $systemDefault;

        if ($isDefaultUser) {
            return [
                'value' => $defaultOverrides[$field] ?? $systemValue,
                'hasOverride' => isset($defaultOverrides[$field]),
            ];
        }

        return [
            'value' => $userOverrides[$field] ?? $defaultOverrides[$field] ?? $systemValue,
            'hasOverride' => isset($userOverrides[$field]),
        ];
    }

    protected function getExerciseTypeConfig(Exercise $exercise): mixed
    {
        $accessor = $exercise->type->getConfigAccessor();

        return $exercise->config?->{$accessor};
    }

    protected function buildMinimalExerciseConfig(int $sets, int $sessionsPerWeek): \App\Training\Data\ExerciseConfig
    {
        return new \App\Training\Data\ExerciseConfig(
            starting1RM: 0,
            target1RM: 0,
            target: 0,
            startingReps: 0,
            sets: $sets,
            sessionsPerWeek: $sessionsPerWeek,
            deloadEnabled: false,
            deloadSetsReduction: 0,
        );
    }
}
