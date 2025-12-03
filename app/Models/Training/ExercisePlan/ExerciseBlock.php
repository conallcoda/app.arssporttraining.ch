<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class ExerciseBlock extends AbstractData
{
    public function __construct(
        public AthleteExerciseConfig $config,
        #[DataCollectionOf(ExerciseWeek::class)]
        public array $weeks = [],
    ) {}

    public static function example(?AthleteExerciseConfig $config = null): self
    {
        return new self(
            config: $config ?? AthleteExerciseConfig::example(),
            weeks: [
                ExerciseWeek::example(),
                ExerciseWeek::example(),
                ExerciseWeek::example(),
                ExerciseWeek::example(),
                ExerciseWeek::example(),
            ],
        );
    }
}
