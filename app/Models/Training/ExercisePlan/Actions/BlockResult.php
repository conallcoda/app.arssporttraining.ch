<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Data\AbstractData;
use App\Models\Training\ExercisePlan\Actions\BlockAction;
use App\Models\Training\ExercisePlan\ExerciseBlock;

class BlockResult extends AbstractData
{

    public function __construct(
        public BlockAction $action,
        public ExerciseBlock $previous,
        public ExerciseBlock $current,
    ) {}

    public function title(): string
    {
        return $this->action->title();
    }
}
