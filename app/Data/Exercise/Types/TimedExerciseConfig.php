<?php

namespace App\Data\Exercise\Types;

use App\Cms\Data\AbstractConfig;
use App\Data\Exercise\ExerciseDimensions;
use App\Form\Fields\Exercise as Fields;

class TimedExerciseConfig extends AbstractConfig
{
    public function __construct(
        public int $duration = 0,
        public int $rest = 30,
    ) {}

    /** @return ExerciseDimensions[] */
    public function dimensions(): array
    {
        return [
            ExerciseDimensions::Duration,
            ExerciseDimensions::Rest,
        ];
    }

    public function badgeFields(): array
    {
        return ['duration', 'rest'];
    }

    public static function getFields(array $data = []): array
    {
        return [
            Fields\Duration::make('duration'),
            Fields\Rest::make('rest'),
        ];
    }
}
