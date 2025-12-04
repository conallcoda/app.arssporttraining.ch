<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Data\Form\FluxField;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;

class CreateEmptyBlock extends BlockAction
{
    public function __construct(
        public int $sets = 4,
    ) {}

    public static function getFields(): array
    {
        return [
            FluxField::number('sets')
                ->label('Sets Per Session')
                ->required()
                ->min(1)
                ->max(20)
                ->suffix('sets')
                ->rules('required|integer|min:1|max:20'),
        ];
    }

    public function apply(ExerciseBlock $block): BlockResult
    {
        $newBlock = $block->mapWeeks(
            fn ($week) => $week->mapSessions(
                fn () => ExerciseSession::fromSetCount($this->sets)
            )
        );

        return $this->result($block, $newBlock);
    }
}
