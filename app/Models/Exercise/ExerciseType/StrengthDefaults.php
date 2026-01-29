<?php

namespace App\Models\Exercise\ExerciseType;

use App\Data\AbstractData;
use App\Data\Form\FluxField;
use App\Models\Contracts\HasForms;
use App\Models\Exercise\Exercise;

class StrengthDefaults extends AbstractData implements HasForms
{
    public function __construct(
        public int $oneRepMaxModifier = 100,
        public int $startingReps = 12,
        public string $timeUnderTension = '3010',
        public int $rest = 30,
    ) {}

    public static function fromExercise(Exercise $exercise): self
    {
        $config = $exercise->extra['type'] ?? [];

        return new self(
            oneRepMaxModifier: (int) ($config['oneRepMaxModifier'] ?: 100),
            startingReps: (int) ($config['startingReps'] ?: 12),
            timeUnderTension: $config['timeUnderTension'] ?: '3010',
            rest: (int) ($config['rest'] ?: 30),
        );
    }

    public static function getFields(): array
    {
        return [
            FluxField::percentage('oneRepMaxModifier')
                ->label('1RM (Modifier)')
                ->default(100),
            FluxField::number('startingReps')
                ->label('Starting Reps')
                ->default(12)
                ->min(1)
                ->suffix('rep(s)')
                ->step(1),
            FluxField::tut('timeUnderTension', true)
                ->default('3010')
                ->label('Time Under Tension'),
            FluxField::number('rest')
                ->label('Rest Between Sets')
                ->default(30)
                ->min(0)
                ->suffix('s')
                ->step(5),
        ];
    }
}
