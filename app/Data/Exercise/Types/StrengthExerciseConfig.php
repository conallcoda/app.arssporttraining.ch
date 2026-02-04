<?php

namespace App\Data\Exercise\Types;

use App\Data\AbstractData;
use App\Form\Fields\Exercise\OneRepMaxModifier;
use App\Form\Fields\Exercise\Rest;
use App\Form\Fields\Exercise\StartingReps;
use App\Form\Fields\Exercise\TimeUnderTension;
use App\Models\Contracts\HasForms;
use App\Models\Exercise\Exercise;

class StrengthExerciseConfig extends AbstractData implements HasForms
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
            OneRepMaxModifier::make('oneRepMaxModifier'),
            StartingReps::make('startingReps'),
            TimeUnderTension::make('timeUnderTension'),
            Rest::make('rest'),
        ];
    }
}
