<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Models\Training\ExercisePlan\ExerciseBlock;

class CreateEmptyBlock extends BlockAction
{
    public function apply(ExerciseBlock $block): BlockResult
    {
        return $this->result($block, $block);
    }
}
