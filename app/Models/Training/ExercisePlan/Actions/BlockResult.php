<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Data\AbstractData;
use App\Models\Training\ExercisePlan\Actions\Casts\BlockActionCast;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use Spatie\LaravelData\Attributes\WithCast;

class BlockResult extends AbstractData
{
    public function __construct(
        #[WithCast(BlockActionCast::class)]
        public BlockAction $action,
        public ExerciseBlock $previous,
        public ExerciseBlock $current,
    ) {}

    public function title(): string
    {
        return $this->action->title();
    }
}
