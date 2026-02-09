<?php

namespace App\Data\Exercise;

use App\Cms\Data\AbstractConfig;
use App\Cms\Data\AbstractData;

class ExerciseConfig extends AbstractData
{
    public function __construct(
        public ?Types\StrengthAutomaticExerciseConfig $strength_automatic = null,
        public ?Types\StrengthManualExerciseConfig $strength_manual = null,
        public ?Types\ConditioningExerciseConfig $conditioning = null,
        public ?Types\TimedExerciseConfig $timed = null,
        public ?Types\CardioExerciseConfig $cardio = null,
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
