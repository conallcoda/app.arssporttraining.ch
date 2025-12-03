<?php

namespace App\Models\Training\Progression\Strategy\Rep;

use App\Data\Form\FluxField;

class FixedRepConfig extends AbstractRepConfig
{
    public function __construct(
        public int $reps = 8,
    ) {}

    public function getStrategyClass(): string
    {
        return FixedRepStrategy::class;
    }

    public function getType(): string
    {
        return 'fixed';
    }

    public function getAnchorRepsForWeek(int $weekIndex, int $blockLength): int
    {
        return $this->reps;
    }

    public function getFields(): array
    {
        return [
            FluxField::number('reps')
                ->label('Reps')
                ->required()
                ->min(1)
                ->max(30)
                ->suffix('reps')
                ->rules('required|integer|min:1|max:30'),
        ];
    }
}
