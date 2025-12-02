<?php

namespace App\Models\Training\Progression\Strategy\Rep;

use Filament\Forms\Components\TextInput;

class ProportionalRepConfig extends AbstractRepConfig
{
    public function __construct(
        public int $startingReps = 14,
        public int $stepDownInterval = 2,
        public int $minimumReps = 6,
    ) {}

    public function getStrategyClass(): string
    {
        return ProportionalRepStrategy::class;
    }

    public function getFields(): array
    {
        return [
            TextInput::make('startingReps')
                ->label('Starting Reps')
                ->numeric()
                ->integer()
                ->default(14)
                ->minValue(1),
            TextInput::make('stepDownInterval')
                ->label('Step Down Interval')
                ->numeric()
                ->integer()
                ->default(2)
                ->minValue(1)
                ->helperText('Number of weeks before reducing reps'),
            TextInput::make('minimumReps')
                ->label('Minimum Reps')
                ->numeric()
                ->integer()
                ->default(6)
                ->minValue(1),
        ];
    }
}
