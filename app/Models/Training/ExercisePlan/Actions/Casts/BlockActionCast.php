<?php

namespace App\Models\Training\ExercisePlan\Actions\Casts;

use App\Models\Training\ExercisePlan\Actions\BlockAction;
use App\Models\Training\ExercisePlan\Actions\CreateEmptyBlock;
use App\Models\Training\ExercisePlan\Actions\SetBlockTarget;
use App\Models\Training\ExercisePlan\Actions\SetPairedReps;
use App\Models\Training\ExercisePlan\Actions\SetWeekProgression;
use App\Models\Training\ExercisePlan\Actions\SetWeekTargets;
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
                'set_block_target' => SetBlockTarget::from($value),
                'set_week_targets' => SetWeekTargets::from($value),
                'set_week_progression' => SetWeekProgression::from($value),
                'set_paired_reps' => SetPairedReps::from($value),
                'set_weights_by_reps_and_derived_one_rep_max' => SetWeightsByRepsAndDerivedOneRepMax::from($value),
                default => CreateEmptyBlock::from($value),
            };
        }

        return new CreateEmptyBlock;
    }
}
