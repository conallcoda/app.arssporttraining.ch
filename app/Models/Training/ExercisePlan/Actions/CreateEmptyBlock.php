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

    public static function helpText(): string
    {
        return 'Creates an empty training block with the specified number of sets per session.';
    }

    public static function getFields(): array
    {
        return [
            FluxField::radioSegmented('sets')
                ->label('Sets Per Session')
                ->required()
                ->options([4 => '4', 5 => '5', 6 => '6'])
                ->rules('required|integer|in:4,5,6')
                ->helpText('The number of sets to include in each training session. More sets increase volume but also fatigue.'),
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
