<?php

namespace App\Models\Training\Progression\Strategy\Rep;

use App\Data\Form\FluxField;

class PairedLadderRepConfig extends AbstractRepConfig
{
    public function __construct(
        public int $startingReps = 14,
        public int $stepDownInterval = 2,
        public int $repDecrement = 2,
        public int $minimumReps = 6,
    ) {}

    public function getStrategyClass(): string
    {
        return PairedLadderRepStrategy::class;
    }

    public function getType(): string
    {
        return 'paired_ladder';
    }

    public function getFields(): array
    {
        return [
            FluxField::number('startingReps')
                ->label('Starting Reps')
                ->required()
                ->min(1)
                ->max(30)
                ->suffix('reps')
                ->rules('required|integer|min:1|max:30'),
            FluxField::number('stepDownInterval')
                ->label('Step Down Interval')
                ->required()
                ->min(1)
                ->max(10)
                ->suffix('weeks')
                ->rules('required|integer|min:1|max:10'),
            FluxField::number('repDecrement')
                ->label('Rep Decrement')
                ->required()
                ->min(1)
                ->max(10)
                ->suffix('reps')
                ->rules('required|integer|min:1|max:10'),
            FluxField::number('minimumReps')
                ->label('Minimum Reps')
                ->required()
                ->min(1)
                ->max(20)
                ->suffix('reps')
                ->rules('required|integer|min:1|max:20'),
        ];
    }
}
