<?php

namespace App\Models\Training\ExercisePlan\Strategies;

use App\Models\Training\ExercisePlan\Actions;

class FixedDecrement extends AbstractStrategy
{
    public static function actions(array $config): array
    {
        $actions = [
            Actions\CreateEmptyBlock::class,
            Actions\SetOneRepMaxBlockTarget::class,
            Actions\SetOneRepMaxWeekTargetsFixedDecrement::class,
            Actions\SetWeekOneRepMaxProgressionFixedDecrement::class,
            Actions\SetPairedReps::class,
            Actions\SetWeightsByRepsAndDerivedOneRepMax::class,
        ];

        return static::mapActions($actions, $config);
    }
}
