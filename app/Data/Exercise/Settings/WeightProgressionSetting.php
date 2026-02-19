<?php

namespace App\Data\Exercise\Settings;

use Coda\Cms\Form\Fields;

class WeightProgressionSetting extends AbstractSetting
{
    public function __construct(
        public ?int $measuredReps = null,
        public ?float $measuredWeight = null,
        public ?int $targetGoal = null,
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
                ->suffix('rep(s)'),
            Fields\Weight::make('measuredWeight')
                ->label('Measured Weight'),
            Fields\Percentage::make('targetGoal')
                ->label('Target Goal')
                ->default(0),
        ];
    }
}
