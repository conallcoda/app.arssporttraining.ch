<?php

namespace App\Data\Exercise\Types;

use App\Data\AbstractConfig;
use App\Form\Fields\Exercise as Fields;
use Illuminate\Database\Eloquent\Model;

class CardioExerciseConfig extends AbstractConfig
{
    public function __construct(
        public int $distance = 0,
        public int $duration = 0,
    ) {}

    public static function accessor(): string
    {
        return 'config.cardio';
    }

    public static function fromExercise(Model $exercise): self
    {
        return static::fromConfig($exercise);
    }

    public static function getFields(): array
    {
        return [
            Fields\Distance::make('distance'),
            Fields\Duration::make('duration'),
        ];
    }
}
