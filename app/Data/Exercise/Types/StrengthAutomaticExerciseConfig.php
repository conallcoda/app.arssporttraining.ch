<?php

namespace App\Data\Exercise\Types;

use App\Cms\Data\AbstractConfig;
use App\Form\Fields\Exercise as Fields;

class StrengthAutomaticExerciseConfig extends AbstractConfig
{
    public function __construct(
        public int $oneRepMaxModifier = 100,
        public int $startingReps = 12,
        public string $timeUnderTension = '3010',
        public int $rest = 30,
    ) {}

    public static function getFields(array $data = []): array
    {
        return [
            Fields\OneRepMaxModifier::make('oneRepMaxModifier'),
            Fields\StartingReps::make('startingReps'),
            Fields\TimeUnderTension::make('timeUnderTension'),
            Fields\Rest::make('rest'),
        ];
    }
}
