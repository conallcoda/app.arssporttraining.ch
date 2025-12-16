<?php

namespace App\Models\Training\ExercisePlan\Strategies;

use App\Models\Training\ExercisePlan\Actions;

class FixedDecrement extends AbstractStrategy
{
    public static function actions(): array
    {
        $actions = [
            Actions\CreateEmptyBlock::class,
            Actions\SetOneRepMaxBlockTarget::class,
            Actions\SetWeightWeekTargetsFixedDecrement::class,
            Actions\SetWeekWeightProgressionFixedDecrement::class,
            Actions\SetPairedReps::class,

        ];

        return $actions;
    }
}
