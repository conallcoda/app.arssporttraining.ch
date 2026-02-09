<?php

namespace App\Data\Exercise\Types;

use App\Cms\Data\AbstractConfig;
use App\Data\Exercise\ExerciseDimensions;
use App\Form\Fields\Exercise as Fields;

class StrengthManualExerciseConfig extends AbstractConfig
{
    public function __construct(
        public float $startingWeight = 0,
        public int $startingReps = 12,
        public string $tempo = '3010',
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
        return ['startingWeight', 'startingReps', 'tempo', 'rest'];
    }

    public static function getFields(array $data = []): array
    {
        return [
            Fields\StartingWeight::make('startingWeight'),
            Fields\StartingReps::make('startingReps'),
            Fields\Tempo::make('tempo'),
            Fields\Rest::make('rest'),
        ];
    }
}
