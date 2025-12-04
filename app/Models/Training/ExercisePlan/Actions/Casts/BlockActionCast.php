<?php

namespace App\Models\Training\ExercisePlan\Actions\Casts;

use App\Models\Training\ExercisePlan\Actions\BlockAction;
use App\Models\Training\ExercisePlan\Actions\CreateEmptyBlock;
use App\Models\Training\ExercisePlan\Actions\SetOneRepMaxBlockTarget;
use App\Models\Training\ExercisePlan\Actions\SetOneRepMaxWeekTargetsFixedDecrement;
use App\Models\Training\ExercisePlan\Actions\SetPairedReps;
use App\Models\Training\ExercisePlan\Actions\SetWeekOneRepMaxProgressionFixedDecrement;
use App\Models\Training\ExercisePlan\Actions\SetWeightsByRepsAndDerivedOneRepMax;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class BlockActionCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): BlockAction
    {
        if ($value instanceof BlockAction) {
            return $value;
        }

        if (is_array($value)) {
            $type = $value['type'] ?? null;
            unset($value['class'], $value['type']);

            return match ($type) {
                'create_empty_block' => CreateEmptyBlock::from($value),
                'set_one_rep_max_block_target' => SetOneRepMaxBlockTarget::from($value),
                'set_one_rep_max_week_targets_fixed_decrement' => SetOneRepMaxWeekTargetsFixedDecrement::from($value),
                'set_week_one_rep_max_progression_fixed_decrement' => SetWeekOneRepMaxProgressionFixedDecrement::from($value),
                'set_paired_reps' => SetPairedReps::from($value),
                'set_weights_by_reps_and_derived_one_rep_max' => SetWeightsByRepsAndDerivedOneRepMax::from($value),
                default => CreateEmptyBlock::from($value),
            };
        }

        return new CreateEmptyBlock;
    }
}
