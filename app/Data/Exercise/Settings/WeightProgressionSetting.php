<?php

namespace App\Data\Exercise\Settings;

use App\Form\Fields\Weight;
use Coda\Cms\Form\Fields;

class WeightProgressionSetting extends AbstractSetting
{
    public function __construct(
        public ?int $measuredReps = 1,
        public ?float $measuredWeight = 50,
        public ?int $targetGoal = 10,
    ) {}

    public function isComplete(): bool
    {
        return $this->measuredWeight !== null
            && $this->measuredWeight > 0
            && $this->measuredReps !== null
            && $this->measuredReps >= 1;
    }

    public static function fields(): array
    {
        return [
            Fields\Number::make('measuredReps')
                ->label('Measured Reps')
                ->min(1)
                ->max(15)
                ->step(1)
                ->default(1)
                ->suffix('rep(s)'),
            Weight::make('measuredWeight')
                ->label('Measured Weight')
                ->default(50),
            Fields\Percentage::make('targetGoal')
                ->label('Target Goal')
                ->default(10),
        ];
    }
}
