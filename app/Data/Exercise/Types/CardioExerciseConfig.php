<?php

namespace App\Data\Exercise\Types;

use App\Data\AbstractConfig;
use App\Form\Fields\Exercise as Fields;

class CardioExerciseConfig extends AbstractConfig
{
    public function __construct(
        public int $distance = 0,
        public int $duration = 0,
    ) {}

    public static function getFields(): array
    {
        return [
            Fields\Distance::make('distance'),
            Fields\Duration::make('duration'),
        ];
    }
}
