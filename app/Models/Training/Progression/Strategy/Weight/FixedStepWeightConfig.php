<?php

namespace App\Models\Training\Progression\Strategy\Weight;

use Filament\Forms\Components\TextInput;

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

    public function getTargetImprovementAsDecimal(): float
    {
        return $this->targetImprovement / 100;
    }

    public function getFields(): array
    {
        return [
            TextInput::make('targetImprovement')
                ->label('Target Improvement')
                ->numeric()
                ->step(0.1)
                ->default(12.5)
                ->suffix('%')
                ->helperText('Target improvement percentage (e.g., 12.5%)'),
            TextInput::make('incrementStep')
                ->label('Increment Step')
                ->numeric()
                ->step(0.5)
                ->default(0.5)
                ->suffix('kg')
                ->helperText('Weight increment step in kg'),
        ];
    }
}
