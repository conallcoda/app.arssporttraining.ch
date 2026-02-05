<?php

namespace App\Data\Exercise;

use App\Cms\Data\AbstractConfig;
use App\Cms\Data\AbstractData;

class ExerciseConfig extends AbstractData
{
    public function __construct(
        public ?Types\CardioExerciseConfig $cardio = null,
        public ?Types\StrengthExerciseConfig $strength = null,
    ) {}

    public static function forType(ExerciseType $type, array $data = []): self
    {
        $accessor = $type->getConfigAccessor();

        return new self(
            ...[$accessor => $type->getConfigClass()::from($data)]
        );
    }

    public function getForType(ExerciseType $type): ?AbstractConfig
    {
        return $this->{$type->getConfigAccessor()};
    }

    public function toBadges(ExerciseType $type): array
    {
        return $this->getForType($type)?->toBadges() ?? [];
    }
}
