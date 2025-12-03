<?php

namespace App\Models\Training\Progression\Strategy\Weight;

use App\Data\Form\FluxField;

class FixedStepWeightConfig extends AbstractWeightConfig
{
    public function __construct(
        public float $targetImprovement = 12.5,
        public float $incrementStep = 0.5,
    ) {}

    public function getStrategyClass(): string
    {
        return FixedStepWeightStrategy::class;
    }

    public function getType(): string
    {
        return 'fixed_step';
    }

    public function getTargetImprovementAsDecimal(): float
    {
        return $this->targetImprovement / 100;
    }

    public function getFields(): array
    {
        return [
            FluxField::number('targetImprovement')
                ->label('Target Improvement')
                ->required()
                ->min(0)
                ->max(100)
                ->step(0.5)
                ->suffix('%')
                ->rules('required|numeric|min:0|max:100'),
            FluxField::number('incrementStep')
                ->label('Increment Step')
                ->required()
                ->min(0.25)
                ->step(0.25)
                ->suffix('kg')
                ->rules('required|numeric|min:0.25'),
        ];
    }
}
