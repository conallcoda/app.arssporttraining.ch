<?php

namespace App\Data\Exercise\Types;

use App\Data\AbstractConfig;
use App\Form\Fields\Exercise as Fields;
use App\Models\Exercise\Exercise;


class StrengthExerciseConfig extends AbstractConfig
{
    public function __construct(
        public int $oneRepMaxModifier = 100,
        public int $startingReps = 12,
        public string $timeUnderTension = '3010',
        public int $rest = 30,
    ) {}

    public static function accessor(): string
    {
        return 'extra.strength';
    }

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
            Fields\OneRepMaxModifier::make('oneRepMaxModifier'),
            Fields\StartingReps::make('startingReps'),
            Fields\TimeUnderTension::make('timeUnderTension'),
            Fields\Rest::make('rest'),
        ];
    }
}
