<?php

namespace App\Models\Training\ExercisePlan\Actions\Casts;

use App\Models\Training\ExercisePlan\Actions\BlockAction;
use App\Models\Training\ExercisePlan\Actions\CreateEmptyBlock;
use App\Models\Training\ExercisePlan\Actions\SetOneRepMaxBlockStart;
use App\Models\Training\ExercisePlan\Actions\SetOneRepMaxBlockTarget;
use App\Models\Training\ExercisePlan\Actions\SetOneRepMaxWeekTargetsFixedDecrement;
use App\Models\Training\ExercisePlan\Actions\SetOneRepMaxWeekTargetsLinear;
use App\Models\Training\ExercisePlan\Actions\SetPairedReps;
use App\Models\Training\ExercisePlan\Actions\SetWeekOneRepMaxProgressionFixedDecrement;
use App\Models\Training\ExercisePlan\Actions\SetWeekOneRepMaxProgressionLinear;
use App\Models\Training\ExercisePlan\Actions\SetWeightsByRepsAndDerivedOneRepMax;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class BlockActionCast implements Cast
{
    private const ACTION_MAP = [
        'create_empty_block' => CreateEmptyBlock::class,
        'set_one_rep_max_block_start' => SetOneRepMaxBlockStart::class,
        'set_one_rep_max_block_target' => SetOneRepMaxBlockTarget::class,
        'set_one_rep_max_week_targets_fixed_decrement' => SetOneRepMaxWeekTargetsFixedDecrement::class,
        'set_one_rep_max_week_targets_linear' => SetOneRepMaxWeekTargetsLinear::class,
        'set_week_one_rep_max_progression_fixed_decrement' => SetWeekOneRepMaxProgressionFixedDecrement::class,
        'set_week_one_rep_max_progression_linear' => SetWeekOneRepMaxProgressionLinear::class,
        'set_paired_reps' => SetPairedReps::class,
        'set_weights_by_reps_and_derived_one_rep_max' => SetWeightsByRepsAndDerivedOneRepMax::class,
    ];

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): BlockAction
    {
        if ($value instanceof BlockAction) {
            return $value;
        }

        if (is_array($value)) {
            $type = $value['type'] ?? null;
            $class = $value['class'] ?? null;
            unset($value['class'], $value['type']);

            if ($type && isset(self::ACTION_MAP[$type])) {
                return self::ACTION_MAP[$type]::from($value);
            }

            if ($class && class_exists($class) && is_subclass_of($class, BlockAction::class)) {
                return $class::from($value);
            }
        }

        return new CreateEmptyBlock;
    }
}
