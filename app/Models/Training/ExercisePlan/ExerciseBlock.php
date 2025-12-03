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

    public function mapWeeks(callable $transformer): self
    {
        return new self(
            config: $this->config,
            weeks: array_map($transformer, $this->weeks, array_keys($this->weeks))
        );
    }

    public function withWeeks(array $weeks): self
    {
        return new self(
            config: $this->config,
            weeks: $weeks,
        );
    }

    public function lastWeek(): ?ExerciseWeek
    {
        return $this->weeks[count($this->weeks) - 1] ?? null;
    }

    public function lastWeekIndex(): int
    {
        return max(0, count($this->weeks) - 1);
    }

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
