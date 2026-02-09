<?php

namespace App\Data\Exercise\Types;

use App\Cms\Data\AbstractConfig;
use App\Form\Fields\Exercise as Fields;

class StrengthManualExerciseConfig extends AbstractConfig
{
    public function __construct(
        public float $startingWeight = 0,
        public int $startingReps = 12,
        public string $timeUnderTension = '3010',
        public int $rest = 30,
    ) {}

    public static function getFields(array $data = []): array
    {
        return [
            Fields\StartingWeight::make('startingWeight'),
            Fields\StartingReps::make('startingReps'),
            Fields\TimeUnderTension::make('timeUnderTension'),
            Fields\Rest::make('rest'),
        ];
    }
}
