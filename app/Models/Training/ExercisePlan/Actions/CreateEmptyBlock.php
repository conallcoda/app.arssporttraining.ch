<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Models\Training\ExercisePlan\ExerciseBlock;

class CreateEmptyBlock extends BlockAction
{
    public function getType(): string
    {
        return 'create_empty_block';
    }

    public function apply(ExerciseBlock $block): BlockResult
    {
        return new BlockResult(
            action: $this,
            previous: $block,
            current: $block,
        );
    }
}
