<?php

namespace App\Data\Exercise\Types;

use App\Cms\Data\AbstractConfig;
use App\Data\Exercise\ExerciseDimensions;
use App\Form\Fields\Exercise as Fields;

class StrengthAutomaticExerciseConfig extends AbstractConfig
{
    public function __construct(
        public int $oneRepMaxModifier = 100,
        public int $startingReps = 12,
        public string $timeUnderTension = '3010',
        public int $rest = 30,
    ) {}

    /** @return ExerciseDimensions[] */
    public function dimensions(): array
    {
        return [
            ExerciseDimensions::Reps,
            ExerciseDimensions::Weight,
            ExerciseDimensions::Tempo,
            ExerciseDimensions::Rest,
        ];
    }

    public function badgeFields(): array
    {
        return ['oneRepMaxModifier', 'startingReps', 'timeUnderTension', 'rest'];
    }

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
