<?php

namespace App\Models\Training\ExercisePlan\Strategies;

use App\Models\Training\ExercisePlan\Actions;

class LinearProgression extends AbstractStrategy
{
    public static function actions(array $config): array
    {
        $actions = [
            Actions\CreateEmptyBlock::class,
            Actions\SetOneRepMaxWeekTargetsLinear::class,
            Actions\SetWeekOneRepMaxProgressionLinear::class,
            Actions\SetPairedReps::class,
            Actions\SetWeightsByRepsAndDerivedOneRepMax::class,
        ];

        return static::mapActions($actions, $config);
    }
}
