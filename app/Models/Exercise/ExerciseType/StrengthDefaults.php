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
        public int $startingReps = 1,
        public string $timeUnderTension = '03-01-03-01',
        public int $rest = 30,
    ) {}

    public static function fromExercise(Exercise $exercise): self
    {
        $config = $exercise->extra['type'] ?? [];

        return new self(
            oneRepMaxModifier: $config['oneRepMaxModifier'] ?? 100,
            startingReps: $config['startingReps'] ?? 1,
            timeUnderTension: $config['timeUnderTension'] ?? '03-01-03-01',
            rest: $config['rest'] ?? 30,
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
            FluxField::tut('timeUnderTension')
                ->default('03-01-03-01')
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
