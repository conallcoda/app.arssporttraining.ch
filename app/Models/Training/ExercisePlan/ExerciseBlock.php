<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class ExerciseBlock extends AbstractData
{
    public function __construct(
        #[DataCollectionOf(ExerciseWeek::class)]
        public array $weeks = [],
    ) {}

    public static function example(): self
    {
        return new self(
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
